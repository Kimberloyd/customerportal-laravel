<?php

namespace App\Http\Controllers;

use App\Events\PurchaseOrderChanged;
use App\Exceptions\UserActionException;
use App\Models\ProductReturn;
use App\Models\ProductReturnItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Support\CustomerScope;
use App\Support\OrderAudit;
use App\Support\OrderNotifications;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductReturnController extends Controller
{
    public const RETURN_WINDOW_DAYS = 7;

    public function store(Request $request, PurchaseOrder $order): RedirectResponse
    {
        $customer = CustomerScope::forCurrentUser();
        abort_unless($customer && $order->customer_id === $customer->id, 403);

        try {
            DB::transaction(function () use ($request, $order, $customer): void {
                $lockedOrder = PurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
                $lockedOrder->load('items');

                $this->assertOrderCanBeReturned($lockedOrder);

                if ($lockedOrder->returns()
                    ->whereIn('status', ProductReturn::OPEN_STATUSES)
                    ->lockForUpdate()
                    ->exists()) {
                    throw new UserActionException('A return request for this order is already being reviewed.');
                }

                $items = $this->validatedItems($request, $lockedOrder);
                $reason = trim((string) $request->input('reason', ''));
                if (mb_strlen($reason) < 10) {
                    throw new UserActionException('Describe the reason for the return in at least 10 characters.');
                }
                if (mb_strlen($reason) > 1000) {
                    throw new UserActionException('Keep the return reason to 1,000 characters or fewer.');
                }

                $return = ProductReturn::create([
                    'purchase_order_id' => $lockedOrder->id,
                    'customer_id' => $customer->id,
                    'requested_by_user_id' => Auth::id(),
                    'status' => ProductReturn::STATUS_REQUESTED,
                    'reason' => $reason,
                    'requested_at' => now(),
                ]);

                foreach ($items as [$item, $quantity]) {
                    ProductReturnItem::create([
                        'product_return_id' => $return->id,
                        'purchase_order_item_id' => $item->id,
                        'quantity' => $quantity,
                    ]);
                }

                OrderAudit::record(
                    $lockedOrder,
                    'Return Requested',
                    'A customer requested a return for '.$this->itemSummary($items).'.',
                    $request,
                );
            });
        } catch (UserActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        OrderNotifications::returnRequested($order);
        PurchaseOrderChanged::dispatch($order->id, 'return-requested');

        return redirect()->route('purchase-orders.show', $order)
            ->with('success', 'Return request sent. Our team will review it.');
    }

    public function update(Request $request, ProductReturn $return): RedirectResponse
    {
        abort_unless(in_array(Auth::user()->role, ['admin', 'employee'], true), 403);

        $nextStatus = trim((string) $request->input('status', ''));
        abort_unless(in_array($nextStatus, [
            ProductReturn::STATUS_APPROVED,
            ProductReturn::STATUS_REJECTED,
            ProductReturn::STATUS_RECEIVED,
        ], true), 422);

        $order = null;
        $notify = null;

        try {
            DB::transaction(function () use ($request, $return, $nextStatus, &$order, &$notify): void {
                $lockedReturn = ProductReturn::whereKey($return->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $order = PurchaseOrder::whereKey($lockedReturn->purchase_order_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $note = trim((string) $request->input('review_note', ''));
                if (mb_strlen($note) > 1000) {
                    throw new UserActionException('Keep the staff note to 1,000 characters or fewer.');
                }

                if ($nextStatus === ProductReturn::STATUS_APPROVED) {
                    if ($lockedReturn->status !== ProductReturn::STATUS_REQUESTED) {
                        throw new UserActionException('Only a requested return can be approved.');
                    }
                    $lockedReturn->status = ProductReturn::STATUS_APPROVED;
                    $lockedReturn->review_note = $note !== '' ? $note : null;
                    $lockedReturn->reviewed_at = now();
                    $lockedReturn->reviewed_by_user_id = Auth::id();
                    $action = 'Return Approved';
                    $details = 'The return request was approved. Arrange collection or delivery with the customer.';
                    $notify = 'approved';
                } elseif ($nextStatus === ProductReturn::STATUS_REJECTED) {
                    if ($lockedReturn->status !== ProductReturn::STATUS_REQUESTED) {
                        throw new UserActionException('Only a requested return can be rejected.');
                    }
                    if ($note === '') {
                        throw new UserActionException('Explain why this return request cannot be approved.');
                    }
                    $lockedReturn->status = ProductReturn::STATUS_REJECTED;
                    $lockedReturn->review_note = $note;
                    $lockedReturn->reviewed_at = now();
                    $lockedReturn->reviewed_by_user_id = Auth::id();
                    $action = 'Return Rejected';
                    $details = 'The return request was declined. See the return details for the reason.';
                    $notify = 'rejected';
                } else {
                    if ($lockedReturn->status !== ProductReturn::STATUS_APPROVED) {
                        throw new UserActionException('Approve this return request before recording the returned products.');
                    }
                    $lockedReturn->status = ProductReturn::STATUS_RECEIVED;
                    $lockedReturn->review_note = $note !== '' ? $note : $lockedReturn->review_note;
                    $lockedReturn->received_at = now();
                    $lockedReturn->received_by_user_id = Auth::id();
                    $action = 'Return Received';
                    $details = 'The approved return was received by the company.';
                    $notify = 'received';
                }

                $lockedReturn->save();
                OrderAudit::record($order, $action, $details, $request);
            });
        } catch (UserActionException $e) {
            return back()->with('error', $e->getMessage());
        }

        OrderNotifications::returnUpdated($order, $notify);
        PurchaseOrderChanged::dispatch($order->id, "return-{$notify}");

        return redirect()->route('purchase-orders.show', $order)
            ->with('success', $this->successMessage($notify));
    }

    private function assertOrderCanBeReturned(PurchaseOrder $order): void
    {
        if ($order->status !== PurchaseOrder::STATUS_COMPLETED || $order->customer_received_at === null) {
            throw new UserActionException('Returns can be requested after the completed order has been confirmed as received.');
        }

        if (now()->greaterThan($order->customer_received_at->copy()->addDays(self::RETURN_WINDOW_DAYS))) {
            throw new UserActionException('The 7-day return request window for this order has ended. Contact our team for help.');
        }
    }

    /**
     * @return array<int, array{0: PurchaseOrderItem, 1: int}>
     */
    private function validatedItems(Request $request, PurchaseOrder $order): array
    {
        $submittedItems = $request->input('items', []);
        if (! is_array($submittedItems)) {
            throw new UserActionException('Select at least one delivered product to return.');
        }

        $orderItems = $order->items->keyBy('id');
        $quantities = [];
        foreach ($submittedItems as $line) {
            if (! is_array($line)) {
                throw new UserActionException('One selected product could not be read. Refresh the page and try again.');
            }

            $itemId = filter_var($line['purchase_order_item_id'] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
            $quantity = filter_var($line['quantity'] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
            $item = $itemId === false ? null : $orderItems->get($itemId);

            if (! $item || $quantity === false || isset($quantities[$itemId])) {
                throw new UserActionException('Select each delivered product once and enter a whole-number quantity.');
            }

            if ($quantity > (int) $item->delivered_quantity) {
                throw new UserActionException("{$item->display_name} can only be returned up to the {$item->delivered_quantity} unit(s) delivered.");
            }

            $quantities[$itemId] = [$item, $quantity];
        }

        if ($quantities === []) {
            throw new UserActionException('Select at least one delivered product to return.');
        }

        $receivedQuantities = ProductReturnItem::query()
            ->whereIn('purchase_order_item_id', array_keys($quantities))
            ->whereHas('productReturn', fn ($query) => $query
                ->where('purchase_order_id', $order->id)
                ->where('status', ProductReturn::STATUS_RECEIVED))
            ->selectRaw('purchase_order_item_id, SUM(quantity) as total')
            ->groupBy('purchase_order_item_id')
            ->pluck('total', 'purchase_order_item_id');

        foreach ($quantities as $itemId => [$item, $quantity]) {
            $available = (int) $item->delivered_quantity - (int) ($receivedQuantities[$itemId] ?? 0);
            if ($quantity > $available) {
                throw new UserActionException("{$item->display_name} has only {$available} delivered unit(s) remaining for return.");
            }
        }

        return array_values($quantities);
    }

    /**
     * @param  array<int, array{0: PurchaseOrderItem, 1: int}>  $items
     */
    private function itemSummary(array $items): string
    {
        return collect($items)
            ->map(fn (array $line) => "{$line[0]->display_name} ({$line[1]} unit(s))")
            ->implode(', ');
    }

    private function successMessage(string $status): string
    {
        return match ($status) {
            'approved' => 'Return request approved. Arrange collection or delivery with the customer.',
            'rejected' => 'Return request declined and the customer was notified.',
            'received' => 'Returned products recorded as received.',
        };
    }
}
