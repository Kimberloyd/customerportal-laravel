<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Support\CustomerScope;
use App\Support\OrderAudit;
use App\Support\PoAttachment;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ports app/purchase_orders/purchase_order_routes.py (po_list,
 * po_create, po_view, po_attachment only -- po_edit/po_complete/
 * po_receive/po_cancel/po_print and order notifications are Phase 4).
 */
class PurchaseOrderController extends Controller
{
    private const PENDING_STATUSES = [
        PurchaseOrder::STATUS_SUBMITTED,
        PurchaseOrder::STATUS_PARTIAL,
        PurchaseOrder::STATUS_PROCESSING,
    ];

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
            $query->whereHas('customer', function ($q) use ($search) {
                $q->whereRaw('LOWER(company_name) LIKE ?', ['%'.strtolower($search).'%']);
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
            ->paginate(25)
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

        $products = Product::query()
            ->where('is_active', true)
            ->orderBy('product_name')
            ->get(['id', 'product_name', 'generic_name', 'sku', 'unit', 'description', 'unit_price'])
            ->toArray();

        return Inertia::render('PurchaseOrders/Create', [
            'customers' => $customers,
            'products' => $products,
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

        for ($i = 0; $i < $lineCount; $i++) {
            $productId = $productIds[$i] ?? null;
            $productSearch = trim((string) ($productSearches[$i] ?? ''));
            $quantityValue = $quantities[$i] ?? null;

            $product = null;

            if ($productId) {
                $product = Product::find($productId);
                if (! $product) {
                    throw ValidationException::withMessages([
                        "items.{$i}" => 'Selected product no longer exists.',
                    ]);
                }
            } elseif ($productSearch !== '') {
                $pattern = '%'.strtolower($productSearch).'%';
                $matches = Product::query()
                    ->where('is_active', true)
                    ->where(function ($q) use ($pattern) {
                        $q->whereRaw('LOWER(product_name) LIKE ?', [$pattern])
                            ->orWhereRaw('LOWER(generic_name) LIKE ?', [$pattern])
                            ->orWhereRaw('LOWER(sku) LIKE ?', [$pattern])
                            ->orWhereRaw('LOWER(unit) LIKE ?', [$pattern]);
                    })
                    ->get();

                if ($matches->count() === 1) {
                    $product = $matches->first();
                } elseif ($matches->count() > 1) {
                    throw ValidationException::withMessages([
                        "items.{$i}" => 'Multiple products matched your search. Please select the exact product from the results.',
                    ]);
                } else {
                    throw ValidationException::withMessages([
                        "items.{$i}" => "No product matches \"{$productSearch}\". Please select a product from the search results.",
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
                'items' => 'Please add at least one product line.',
            ]);
        }

        $customerId = $customer?->id ?? (int) $request->input('customer_id');
        if (! $customerId || ! Customer::where('id', $customerId)->exists()) {
            throw ValidationException::withMessages([
                'customer_id' => 'Select a valid customer.',
            ]);
        }

        $attachmentFile = $request->file('po_attachment');
        if ($attachmentFile) {
            $request->validate([
                'po_attachment' => 'file|max:8192',
            ]);
        }

        $poNumber = 'PO-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(2));
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
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'delivered_quantity' => 0,
                        'unit_price' => $unitPrice,
                        'line_total' => $quantity * $unitPrice,
                        'product_name' => $product->product_name,
                        'sku' => $product->sku,
                        'unit' => $product->unit,
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

        // Phase 4: notify_purchase_order_submitted() equivalent plugs in
        // here for customer-initiated orders (email/portal-message/
        // Messenger) -- not built yet, deliberately deferred.

        return redirect()->route('purchase-orders.index')->with('success', 'Order created successfully.');
    }

    public function show(PurchaseOrder $order): Response
    {
        $this->authorizeOrderAccess($order);

        $order->load(['customer', 'items.product', 'auditLogs.actor']);

        $isCustomerViewer = Auth::user()->role === 'customer';

        return Inertia::render('PurchaseOrders/Show', [
            'order' => [
                'id' => $order->id,
                'po_number' => $order->po_number,
                'submitted_at' => $order->submitted_at?->toIso8601String(),
                'updated_at' => $order->updated_at?->toIso8601String(),
                'status' => $order->status,
                'remarks' => $order->remarks,
                'total' => $order->total,
                'has_attachment' => (bool) $order->po_file,
                'customer' => [
                    'name' => $order->customer->company_name,
                    'email' => $order->customer->email,
                    'phone' => $order->customer->phone,
                    'address' => $order->customer->address,
                ],
                'items' => $order->items->map(fn (PurchaseOrderItem $item) => [
                    'id' => $item->id,
                    'display_name' => $item->display_name,
                    'quantity' => $item->quantity,
                    'delivered_quantity' => $item->delivered_quantity,
                    'pending_quantity' => $item->pending_quantity,
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
