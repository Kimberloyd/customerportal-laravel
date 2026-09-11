<?php

namespace App\Services;

use App\Models\OrderFollowUp;
use App\Models\ProductReturn;
use App\Models\PurchaseOrder;
use App\Support\ReminderSettings;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;

class OrderFollowUpManager
{
    public const AWAITING_FULFILLMENT = 'awaiting_fulfillment';

    public const STALLED_PARTIAL = 'stalled_partial';

    public const AWAITING_CUSTOMER_CLOSE = 'awaiting_customer_close';

    public const RETURN_REVIEW = 'return_review';

    public const RETURN_RECEIPT = 'return_receipt';

    public const ORDER_KINDS = [
        self::AWAITING_FULFILLMENT,
        self::STALLED_PARTIAL,
        self::AWAITING_CUSTOMER_CLOSE,
    ];

    public function syncOrder(PurchaseOrder $order, ?CarbonInterface $activityAt = null): void
    {
        if (! Schema::hasTable('order_follow_ups')) {
            return;
        }

        $at = $activityAt ?? $order->updated_at ?? $order->submitted_at ?? now();

        if ($order->trashed() || in_array($order->status, PurchaseOrder::TERMINAL_STATUSES, true)) {
            $this->resolve($order, self::ORDER_KINDS);

            return;
        }

        if (in_array($order->status, [PurchaseOrder::STATUS_SUBMITTED, PurchaseOrder::STATUS_RETURNED], true)) {
            $this->activate($order, self::AWAITING_FULFILLMENT, $at, refresh: $activityAt !== null);
            $this->resolve($order, [self::STALLED_PARTIAL, self::AWAITING_CUSTOMER_CLOSE]);

            return;
        }

        if ($order->status === PurchaseOrder::STATUS_PARTIAL) {
            $this->activate($order, self::STALLED_PARTIAL, $at, refresh: $activityAt !== null);
            $this->resolve($order, [self::AWAITING_FULFILLMENT, self::AWAITING_CUSTOMER_CLOSE]);

            return;
        }

        if ($order->status === PurchaseOrder::STATUS_PROCESSED) {
            $this->activate($order, self::AWAITING_CUSTOMER_CLOSE, $at, refresh: $activityAt !== null);
            $this->resolve($order, [self::AWAITING_FULFILLMENT, self::STALLED_PARTIAL]);
        }
    }

    public function syncReturn(ProductReturn $return): void
    {
        if (! Schema::hasTable('order_follow_ups')) {
            return;
        }

        $order = $return->purchaseOrder;
        if ($return->status === ProductReturn::STATUS_REQUESTED) {
            $this->activate($order, self::RETURN_REVIEW, $return->requested_at ?? now(), productReturn: $return);
            $this->resolveReturn($return, self::RETURN_RECEIPT);
        } elseif ($return->status === ProductReturn::STATUS_APPROVED) {
            $this->resolveReturn($return, self::RETURN_REVIEW);
            $this->activate($order, self::RETURN_RECEIPT, $return->reviewed_at ?? now(), productReturn: $return);
        } else {
            $this->resolveReturn($return, self::RETURN_REVIEW);
            $this->resolveReturn($return, self::RETURN_RECEIPT);
        }
    }

    public function isApplicable(OrderFollowUp $followUp): bool
    {
        $followUp->loadMissing(['purchaseOrder', 'productReturn']);
        $order = $followUp->purchaseOrder;
        if (! $order || $order->trashed()) {
            return false;
        }

        return match ($followUp->kind) {
            self::AWAITING_FULFILLMENT => in_array($order->status, [PurchaseOrder::STATUS_SUBMITTED, PurchaseOrder::STATUS_RETURNED], true),
            self::STALLED_PARTIAL => $order->status === PurchaseOrder::STATUS_PARTIAL,
            self::AWAITING_CUSTOMER_CLOSE => $order->status === PurchaseOrder::STATUS_PROCESSED,
            self::RETURN_REVIEW => $followUp->productReturn?->status === ProductReturn::STATUS_REQUESTED,
            self::RETURN_RECEIPT => $followUp->productReturn?->status === ProductReturn::STATUS_APPROVED,
            default => false,
        };
    }

    private function activate(
        PurchaseOrder $order,
        string $kind,
        CarbonInterface $triggeredAt,
        bool $refresh = false,
        ?ProductReturn $productReturn = null,
    ): void {
        $query = OrderFollowUp::query()
            ->where('purchase_order_id', $order->id)
            ->where('kind', $kind);
        if ($productReturn) {
            $query->where('product_return_id', $productReturn->id);
        } else {
            $query->whereNull('product_return_id');
        }

        $latest = $query->latest('cycle')->lockForUpdate()->first();
        if ($latest && in_array($latest->status, [OrderFollowUp::STATUS_PENDING, OrderFollowUp::STATUS_DISPATCHING, OrderFollowUp::STATUS_ESCALATED, OrderFollowUp::STATUS_PAUSED], true)) {
            if ($refresh && $latest->status === OrderFollowUp::STATUS_PENDING) {
                $latest->update([
                    'triggered_at' => $triggeredAt,
                    'next_due_at' => $triggeredAt->copy()->addHours(ReminderSettings::threshold($kind, 'reminder')),
                    'level' => 'reminder',
                    'attempt_count' => 0,
                    'last_error_at' => null,
                ]);
            }

            return;
        }

        // Cycles are allocated across the order and kind because the database
        // uniqueness boundary also covers separate returns on the same order.
        $nextCycle = (int) OrderFollowUp::query()
            ->where('purchase_order_id', $order->id)
            ->where('kind', $kind)
            ->lockForUpdate()
            ->max('cycle') + 1;

        OrderFollowUp::create([
            'purchase_order_id' => $order->id,
            'product_return_id' => $productReturn?->id,
            'kind' => $kind,
            'cycle' => $nextCycle,
            'level' => 'reminder',
            'status' => OrderFollowUp::STATUS_PENDING,
            'triggered_at' => $triggeredAt,
            'next_due_at' => $triggeredAt->copy()->addHours(ReminderSettings::threshold($kind, 'reminder')),
        ]);
    }

    /** @param array<int, string> $kinds */
    private function resolve(PurchaseOrder $order, array $kinds): void
    {
        OrderFollowUp::query()
            ->where('purchase_order_id', $order->id)
            ->whereIn('kind', $kinds)
            ->whereIn('status', [OrderFollowUp::STATUS_PENDING, OrderFollowUp::STATUS_DISPATCHING, OrderFollowUp::STATUS_ESCALATED, OrderFollowUp::STATUS_PAUSED])
            ->update(['status' => OrderFollowUp::STATUS_RESOLVED, 'resolved_at' => now(), 'next_due_at' => null]);
    }

    private function resolveReturn(ProductReturn $return, string $kind): void
    {
        OrderFollowUp::query()
            ->where('product_return_id', $return->id)
            ->where('kind', $kind)
            ->where('status', '!=', OrderFollowUp::STATUS_RESOLVED)
            ->update(['status' => OrderFollowUp::STATUS_RESOLVED, 'resolved_at' => now(), 'next_due_at' => null]);
    }
}
