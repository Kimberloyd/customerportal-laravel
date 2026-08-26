<?php

namespace App\Http\Controllers;

use App\Events\PurchaseOrderChanged;
use App\Exceptions\UserActionException;
use App\Models\Customer;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Support\CustomerScope;
use App\Support\InventoryApiClient;
use App\Support\OrderAudit;
use App\Support\OrderNotifications;
use App\Support\PoAttachment;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ports app/purchase_orders/purchase_order_routes.py: po_list,
 * po_create, po_view, po_attachment, po_edit, po_complete, po_receive,
 * po_cancel, po_print.
 */
class PurchaseOrderController extends Controller
{
    private const PENDING_STATUSES = [
        PurchaseOrder::STATUS_SUBMITTED,
        PurchaseOrder::STATUS_PARTIAL,
        PurchaseOrder::STATUS_PROCESSING,
    ];

    public function __construct(private readonly InventoryApiClient $inventory) {}

    public function index(Request $request): Response
    {
        $customer = CustomerScope::forCurrentUser();

        $search = trim((string) $request->query('search', ''));
        $dateFilter = trim((string) $request->query('date_filter', 'all')) ?: 'all';
        $month = trim((string) $request->query('month', now()->format('Y-m'))) ?: now()->format('Y-m');
        $startDate = trim((string) $request->query('start_date', ''));
        $endDate = trim((string) $request->query('end_date', ''));
        $statusFilter = trim((string) $request->query('status', 'all')) ?: 'all';

        $query = PurchaseOrder::query()->with(['customer', 'items']);
        if ($customer) {
            $query->where('customer_id', $customer->id);
        }

        if ($search !== '') {
            $pattern = '%'.strtolower($search).'%';
            $query->where(function ($q) use ($pattern) {
                $q->whereRaw('LOWER(po_number) LIKE ?', [$pattern])
                    ->orWhereHas('customer', function ($q) use ($pattern) {
                        $q->whereRaw('LOWER(company_name) LIKE ?', [$pattern]);
                    });
            });
        }

        if ($dateFilter === 'month') {
            $periodStart = CarbonImmutable::createFromFormat('!Y-m', $month, 'UTC');
            if ($periodStart) {
                $periodEnd = $periodStart->addMonthNoOverflow();
                $query->where('submitted_at', '>=', $periodStart)
                    ->where('submitted_at', '<', $periodEnd);
            } else {
                $dateFilter = 'all';
            }
        } elseif ($dateFilter === 'custom') {
            $periodStart = $this->parseDateOrNull($startDate);
            $selectedEnd = $this->parseDateOrNull($endDate);
            if ($periodStart && $selectedEnd && $selectedEnd->gte($periodStart)) {
                $query->where('submitted_at', '>=', $periodStart)
                    ->where('submitted_at', '<', $selectedEnd->addDay());
            } else {
                $dateFilter = 'all';
            }
        } elseif ($dateFilter !== 'all') {
            $dateFilter = 'all';
        }

        if ($statusFilter === 'active') {
            $query->whereIn('status', self::PENDING_STATUSES);
        } elseif ($statusFilter === 'partial') {
            $query->whereIn('status', PurchaseOrder::IN_PROGRESS_STATUSES);
        } elseif (in_array($statusFilter, [PurchaseOrder::STATUS_SUBMITTED, PurchaseOrder::STATUS_COMPLETED], true)) {
            $query->where('status', $statusFilter);
        } else {
            $statusFilter = 'all';
        }

        $orders = $query->orderByDesc('submitted_at')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (PurchaseOrder $order) => $this->serializeForList($order));

        return Inertia::render('PurchaseOrders/Index', [
            'orders' => $orders,
            'filters' => [
                'search' => $search,
                'date_filter' => $dateFilter,
                'month' => $month,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $statusFilter,
            ],
        ]);
    }

    public function create(): Response
    {
        $customer = CustomerScope::forCurrentUser();

        $customers = $customer
            ? [['id' => $customer->id, 'company_name' => $customer->company_name]]
            : Customer::query()->orderBy('company_name')->get(['id', 'company_name'])->toArray();

        return Inertia::render('PurchaseOrders/Create', [
            'customers' => $customers,
            'products' => Inertia::defer(
                fn () => $this->activeProducts(cached: true)
                    ->sortBy('product_name', SORT_STRING | SORT_FLAG_CASE)
                    ->values()
                    ->all(),
                'catalog',
            ),
            'lockedCustomerId' => $customer?->id,
        ]);
    }

    public function store(Request $request)
    {
        $customer = CustomerScope::forCurrentUser();

        $productIds = $request->input('product_id', []);
        $productSearches = $request->input('product_search', []);
        $quantities = $request->input('quantity', []);

        $lineCount = max(count($productIds), count($productSearches), count($quantities));
        $lineItems = [];
        $products = null;

        for ($i = 0; $i < $lineCount; $i++) {
            $productId = $productIds[$i] ?? null;
            $productSearch = trim((string) ($productSearches[$i] ?? ''));
            $quantityValue = $quantities[$i] ?? null;

            $product = null;

            if ($productId || $productSearch !== '') {
                $products ??= $this->activeProducts();
            }

            if ($productId) {
                $product = $products->firstWhere('id', (int) $productId);
                if (! $product) {
                    throw ValidationException::withMessages([
                        "items.{$i}" => 'Selected product no longer exists.',
                    ]);
                }
            } elseif ($productSearch !== '') {
                $needle = strtolower($productSearch);
                $matches = $products->filter(
                    fn ($p) => str_contains(strtolower((string) $p->product_name), $needle)
                        || str_contains(strtolower((string) $p->generic_name), $needle)
                        || str_contains(strtolower((string) $p->sku), $needle)
                        || str_contains(strtolower((string) $p->unit), $needle),
                );

                if ($matches->count() === 1) {
                    $product = $matches->first();
                } elseif ($matches->count() > 1) {
                    throw ValidationException::withMessages([
                        "items.{$i}" => 'Multiple products match this search. Select the exact product from the results.',
                    ]);
                } else {
                    throw ValidationException::withMessages([
                        "items.{$i}" => "No product matches \"{$productSearch}\". Choose a product from the search results.",
                    ]);
                }
            } else {
                continue;
            }

            $quantity = (int) $quantityValue;
            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    "items.{$i}" => 'Enter a quantity of at least 1 for each product line.',
                ]);
            }

            $lineItems[] = [$product, $quantity];
        }

        if ($lineItems === []) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one product line.',
            ]);
        }

        $customerId = $customer?->id ?? (int) $request->input('customer_id');
        if (! $customerId || ! Customer::where('id', $customerId)->exists()) {
            throw ValidationException::withMessages([
                'customer_id' => 'Select a customer from the list.',
            ]);
        }

        $attachmentFile = $request->file('po_attachment');
        if ($attachmentFile) {
            $request->validate([
                'po_attachment' => 'file|max:8192',
            ]);
        }

        $poNumber = trim((string) $request->input('po_number', ''));
        if ($poNumber === '') {
            throw ValidationException::withMessages([
                'po_number' => 'Enter a PO number.',
            ]);
        }
        if (PurchaseOrder::where('po_number', $poNumber)->exists()) {
            throw ValidationException::withMessages([
                'po_number' => 'This PO number is already in use.',
            ]);
        }

        $storedAttachment = null;

        if ($attachmentFile) {
            try {
                $storedAttachment = PoAttachment::save($attachmentFile, $poNumber);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages([
                    'po_attachment' => $e->getMessage(),
                ]);
            }
        }

        try {
            $order = DB::transaction(function () use ($poNumber, $customerId, $request, $storedAttachment, $lineItems) {
                $order = PurchaseOrder::create([
                    'po_number' => $poNumber,
                    'customer_id' => $customerId,
                    'remarks' => $request->input('remarks'),
                    'po_file' => $storedAttachment,
                    'status' => PurchaseOrder::STATUS_SUBMITTED,
                    'submitted_at' => now(),
                ]);

                foreach ($lineItems as [$product, $quantity]) {
                    $unitPrice = $product->unit_price ?? 0;
                    PurchaseOrderItem::create([
                        'purchase_order_id' => $order->id,
                        'quantity' => $quantity,
                        'delivered_quantity' => 0,
                        'unit_price' => $unitPrice,
                        'line_total' => $quantity * $unitPrice,
                        'product_name' => $product->product_name,
                        'generic_name' => $product->generic_name,
                        'sku' => $product->sku,
                        'unit' => $product->unit,
                        'dosage' => $product->dosage,
                        'description' => $product->description,
                    ]);
                }

                OrderAudit::record($order, 'Order Created', 'Created with '.count($lineItems).' product line(s).', $request);

                return $order;
            });
        } catch (\Throwable $e) {
            if ($storedAttachment) {
                PoAttachment::delete($storedAttachment);
            }
            throw $e;
        }

        if ($customer) {
            OrderNotifications::submitted($order);
        }

        PurchaseOrderChanged::dispatch($order->id, 'created');

        return redirect()->route('purchase-orders.index')->with('success', 'Order created.');
    }

    public function edit(PurchaseOrder $order): Response
    {
        $this->authorizeOrderAccess($order);

        $order->load(['customer', 'items']);

        $customer = CustomerScope::forCurrentUser();
        $customers = $customer
            ? [['id' => $customer->id, 'company_name' => $customer->company_name]]
            : Customer::query()->orderBy('company_name')->get(['id', 'company_name'])->toArray();

        $isTerminal = in_array($order->status, PurchaseOrder::TERMINAL_STATUSES, true);

        return Inertia::render('PurchaseOrders/Edit', [
            'order' => [
                'id' => $order->id,
                'po_number' => $order->po_number,
                'customer_id' => $order->customer_id,
                'remarks' => $order->remarks,
                'has_attachment' => (bool) $order->po_file,
                'is_terminal' => $isTerminal,
                'items' => $order->items->map(fn (PurchaseOrderItem $item) => [
                    'id' => $item->id,
                    'display_name' => $item->display_name,
                    'quantity' => $item->quantity,
                    'delivered_quantity' => $item->delivered_quantity,
                ]),
            ],
            'customers' => $customers,
            'lockedCustomerId' => $customer?->id,
        ]);
    }

    public function update(Request $request, PurchaseOrder $order)
    {
        $this->authorizeOrderAccess($order);

        $previousCustomerId = $order->customer_id;
        $attachmentToDeleteAfterCommit = null;
        $newlySavedAttachment = null;

        try {
            DB::transaction(function () use (
                $request,
                $order,
                &$attachmentToDeleteAfterCommit,
                &$newlySavedAttachment,
            ) {
                $locked = PurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
                $locked->load(['items', 'customer']);

                $isTerminal = in_array($locked->status, PurchaseOrder::TERMINAL_STATUSES, true);
                $changes = [];
                $customer = CustomerScope::forCurrentUser();

                if (! $isTerminal) {
                    $customerId = $customer?->id ?? (int) $request->input('customer_id');
                    $newCustomer = Customer::find($customerId);
                    if (! $newCustomer) {
                        throw new UserActionException('Select a customer from the list.');
                    }

                    $proposedQuantities = [];
                    foreach ($locked->items as $item) {
                        $quantity = (int) $request->input("quantity_{$item->id}", 0);
                        $delivered = $item->delivered_quantity ?? 0;

                        if ($quantity < 1) {
                            throw new UserActionException('Enter an ordered quantity of at least 1.');
                        }
                        if ($quantity < $delivered) {
                            throw new UserActionException(
                                "{$item->display_name} quantity cannot be lower than {$delivered} already delivered."
                            );
                        }
                        $proposedQuantities[$item->id] = $quantity;
                    }

                    if ($locked->customer_id !== $newCustomer->id) {
                        $changes[] = "Customer changed from {$locked->customer->company_name} to {$newCustomer->company_name}.";
                        $locked->customer_id = $newCustomer->id;
                    }

                    foreach ($locked->items as $item) {
                        $quantity = $proposedQuantities[$item->id];
                        if ($item->quantity !== $quantity) {
                            $changes[] = "{$item->display_name} quantity changed from {$item->quantity} to {$quantity}.";
                            $item->quantity = $quantity;
                            $item->line_total = $quantity * ($item->unit_price ?? 0);
                            $item->save();
                        }
                    }

                    $locked->load('items');
                    $locked->updateDeliveryStatus();
                }

                $newRemarks = trim((string) $request->input('remarks', '')) ?: null;
                if ($locked->remarks !== $newRemarks) {
                    $changes[] = 'Remarks updated.';
                    $locked->remarks = $newRemarks;
                }

                if (! $isTerminal) {
                    if ($request->boolean('remove_attachment') && $locked->po_file) {
                        $attachmentToDeleteAfterCommit = $locked->po_file;
                        $locked->po_file = null;
                        $changes[] = 'Attachment removed.';
                    }

                    $newAttachment = $request->file('po_attachment');
                    if ($newAttachment) {
                        try {
                            $newlySavedAttachment = PoAttachment::save($newAttachment, $locked->po_number);
                        } catch (\InvalidArgumentException $e) {
                            throw new UserActionException($e->getMessage());
                        }
                        if ($locked->po_file) {
                            $attachmentToDeleteAfterCommit = $locked->po_file;
                        }
                        $locked->po_file = $newlySavedAttachment;
                        $changes[] = 'Attachment updated.';
                    }
                }

                if ($changes === []) {
                    throw new UserActionException('Nothing changed. Update at least one order detail before saving.');
                }

                $locked->save();
                OrderAudit::record($locked, 'Order Updated', implode(' ', $changes), $request);
            });
        } catch (UserActionException $e) {
            if ($newlySavedAttachment) {
                PoAttachment::delete($newlySavedAttachment);
            }

            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            if ($newlySavedAttachment) {
                PoAttachment::delete($newlySavedAttachment);
            }

            throw $e;
        }

        if ($attachmentToDeleteAfterCommit) {
            PoAttachment::delete($attachmentToDeleteAfterCommit);
        }

        PurchaseOrderChanged::dispatch($order->id, 'updated', $previousCustomerId);

        return redirect()->route('purchase-orders.show', $order->id)->with('success', 'Order updated.');
    }

    public function complete(Request $request, PurchaseOrder $order)
    {
        $this->authorizeOrderAccess($order);
        abort_if(Auth::user()->role === 'customer', 403);

        try {
            DB::transaction(function () use ($request, $order) {
                $locked = PurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
                $locked->load('items');

                if (in_array($locked->status, PurchaseOrder::TERMINAL_STATUSES, true)) {
                    throw new UserActionException("This order is already {$locked->status} and cannot be completed.");
                }

                foreach ($locked->items as $item) {
                    $item->delivered_quantity = $item->quantity;
                    $item->save();
                }

                $locked->status = PurchaseOrder::STATUS_COMPLETED;
                $locked->completed_at = now();
                $locked->save();

                OrderAudit::record($locked, 'Order Completed', 'All ordered quantities were marked delivered.', $request);
            });
        } catch (UserActionException $e) {
            return redirect()->route('purchase-orders.show', $order->id)->with('error', $e->getMessage());
        }

        PurchaseOrderChanged::dispatch($order->id, 'completed');

        return redirect()->route('purchase-orders.index')->with('success', 'Order marked as completed.');
    }

    public function receive(Request $request, PurchaseOrder $order)
    {
        $this->authorizeOrderAccess($order);
        abort_if(Auth::user()->role === 'customer', 403);

        try {
            DB::transaction(function () use ($request, $order) {
                $locked = PurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
                $locked->load('items');

                if (in_array($locked->status, PurchaseOrder::TERMINAL_STATUSES, true)) {
                    throw new UserActionException("This order is already {$locked->status} and cannot receive deliveries.");
                }

                $receivedAny = false;
                $deliveryChanges = [];

                foreach ($locked->items as $item) {
                    $receiveQuantity = (int) $request->input("received_{$item->id}", 0);

                    if ($receiveQuantity < 0) {
                        continue;
                    }
                    if ($receiveQuantity > $item->pending_quantity) {
                        throw new UserActionException('The received quantity is higher than the quantity still pending. Enter the pending quantity or less.');
                    }
                    if ($receiveQuantity > 0) {
                        $item->delivered_quantity = ($item->delivered_quantity ?? 0) + $receiveQuantity;
                        $item->save();
                        $receivedAny = true;
                        $deliveryChanges[] = "{$item->display_name}: {$receiveQuantity} unit(s) delivered.";
                    }
                }

                if (! $receivedAny) {
                    throw new UserActionException('Enter a received quantity for at least one product.');
                }

                $locked->load('items');
                $locked->updateDeliveryStatus();
                $locked->save();

                OrderAudit::record($locked, 'Fulfillment Updated', implode(' ', $deliveryChanges), $request);
            });
        } catch (UserActionException $e) {
            return redirect()->route('purchase-orders.show', $order->id)->with('error', $e->getMessage());
        }

        PurchaseOrderChanged::dispatch($order->id, 'fulfillment-updated');

        return redirect()->route('purchase-orders.show', $order->id)->with('success', 'Delivery quantities updated.');
    }

    public function cancel(Request $request, PurchaseOrder $order)
    {
        $this->authorizeOrderAccess($order);

        try {
            DB::transaction(function () use ($request, $order) {
                $locked = PurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();

                if (in_array($locked->status, PurchaseOrder::TERMINAL_STATUSES, true)) {
                    throw new UserActionException("This order is already {$locked->status} and cannot be cancelled.");
                }

                $locked->status = PurchaseOrder::STATUS_CANCELLED;
                $locked->save();

                OrderAudit::record($locked, 'Order Cancelled', 'Order status changed to Cancelled.', $request);
            });
        } catch (UserActionException $e) {
            return redirect()->route('purchase-orders.show', $order->id)->with('error', $e->getMessage());
        }

        PurchaseOrderChanged::dispatch($order->id, 'cancelled');

        return redirect()->route('purchase-orders.index')->with('success', 'Order cancelled.');
    }

    public function show(PurchaseOrder $order): Response
    {
        $this->authorizeOrderAccess($order);

        $order->load(['customer', 'items', 'auditLogs.actor']);

        $isCustomerViewer = Auth::user()->role === 'customer';
        $isTerminal = in_array($order->status, PurchaseOrder::TERMINAL_STATUSES, true);

        return Inertia::render('PurchaseOrders/Show', [
            'order' => [
                'id' => $order->id,
                'po_number' => $order->po_number,
                'submitted_at' => $order->submitted_at?->toIso8601String(),
                'updated_at' => $order->updated_at?->toIso8601String(),
                'status' => $order->status,
                'is_terminal' => $isTerminal,
                'remarks' => $order->remarks,
                'total' => $order->total,
                'has_attachment' => (bool) $order->po_file,
                'attachment_kind' => $order->po_file
                    ? (in_array(strtolower(pathinfo($order->po_file, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg'], true)
                        ? 'image'
                        : (strtolower(pathinfo($order->po_file, PATHINFO_EXTENSION)) === 'pdf' ? 'pdf' : 'other'))
                    : null,
                'customer' => [
                    'name' => $order->customer->company_name,
                ],
                'items' => $order->items->map(fn (PurchaseOrderItem $item) => [
                    'id' => $item->id,
                    'display_name' => $item->display_name,
                    'quantity' => $item->quantity,
                    'delivered_quantity' => $item->delivered_quantity,
                    'pending_quantity' => $item->pending_quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                ]),
                'audit_logs' => $order->auditLogs->map(fn ($audit) => [
                    'created_at' => $audit->created_at?->toIso8601String(),
                    'actor_name' => $isCustomerViewer ? null : ($audit->actor?->full_name),
                    'actor_role' => $isCustomerViewer ? null : ($audit->actor_role ?? $audit->actor?->role),
                    'action' => $audit->action,
                    'details' => $audit->details,
                    'remarks' => $audit->remarks,
                ]),
            ],
            'isCustomerViewer' => $isCustomerViewer,
            'canManageFulfillment' => ! $isCustomerViewer,
            'canComplete' => ! $isCustomerViewer && ! $isTerminal,
            'canCancel' => ! $isTerminal,
        ]);
    }

    public function print(Request $request, PurchaseOrder $order): Response
    {
        $this->authorizeOrderAccess($order);

        $order->load(['customer', 'items', 'auditLogs.actor']);

        $output = strtolower(trim((string) $request->query('output', 'printer')));
        if (! in_array($output, ['printer', 'pdf'], true)) {
            $output = 'printer';
        }
        $autoPrint = (string) $request->query('auto_print', '1') === '1';

        return Inertia::render('PurchaseOrders/Print', [
            'output' => $output,
            'autoPrint' => $autoPrint,
            'order' => [
                'id' => $order->id,
                'po_number' => $order->po_number,
                'submitted_at' => $order->submitted_at?->toIso8601String(),
                'updated_at' => $order->updated_at?->toIso8601String(),
                'status' => $order->status,
                'remarks' => $order->remarks,
                'total' => $order->total,
                'customer' => [
                    'name' => $order->customer->company_name,
                ],
                'items' => $order->items->map(fn (PurchaseOrderItem $item) => [
                    'id' => $item->id,
                    'display_name' => $item->display_name,
                    'quantity' => $item->quantity,
                    'delivered_quantity' => $item->delivered_quantity,
                    'pending_quantity' => $item->pending_quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                ]),
                'audit_logs' => $order->auditLogs->map(fn ($audit) => [
                    'created_at' => $audit->created_at?->toIso8601String(),
                    'action' => $audit->action,
                    'details' => $audit->details,
                    'remarks' => $audit->remarks,
                ]),
            ],
        ]);
    }

    public function attachment(PurchaseOrder $order): StreamedResponse
    {
        $this->authorizeOrderAccess($order);

        abort_if(! $order->po_file, 404);

        $path = PoAttachment::path($order->po_file);
        abort_if(! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function serializeForList(PurchaseOrder $order): array
    {
        $item = $order->primary_item;
        $isProcessing = in_array($order->status, PurchaseOrder::IN_PROGRESS_STATUSES, true);

        return [
            'id' => $order->id,
            'po_number' => $order->po_number,
            'submitted_at' => $order->submitted_at?->toIso8601String(),
            'customer_name' => $order->customer?->company_name,
            'is_awaiting_fulfillment' => $order->is_awaiting_fulfillment,
            'is_processing' => $isProcessing,
            'item_display_name' => $item?->display_name,
            'ordered_quantity' => (int) $order->items->sum('quantity'),
            'delivered_quantity' => (int) $order->items->sum('delivered_quantity'),
            'balance_units' => $order->balance_units,
            'status' => $order->status,
        ];
    }

    private function authorizeOrderAccess(PurchaseOrder $order): void
    {
        $customer = CustomerScope::forCurrentUser();

        if ($customer && $order->customer_id !== $customer->id) {
            abort(403);
        }
    }

    /**
     * @return Collection<int, object>
     */
    private function activeProducts(bool $cached = false): Collection
    {
        $products = $cached
            ? $this->inventory->cachedProducts(['status' => 'active'])
            : $this->inventory->allProducts(['status' => 'active']);

        return collect($products)
            ->map(fn (array $product) => (object) InventoryApiClient::mapProduct($product));
    }

    private function parseDateOrNull(string $value): ?CarbonImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');
        } catch (\Throwable) {
            return null;
        }

        return $date ?: null;
    }
}
