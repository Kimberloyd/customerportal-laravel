<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'po_number', 'customer_id', 'po_file', 'status', 'remarks',
    'submitted_at', 'updated_at', 'completed_at',
])]
class PurchaseOrder extends Model
{
    use HasPublicId, SoftDeletes;

    public $timestamps = false;

    // Matches app/models.py's ORDER_STATUS_* / ORDER_TERMINAL_STATUSES /
    // ORDER_IN_PROGRESS_STATUSES in the Flask app -- single source of
    // truth for this domain lives there until this table's routes are
    // actually built in a later phase; kept identical here so nothing
    // drifts in the meantime.
    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    // Not part of the ported Flask state machine (see note above) --
    // added so a return that wipes out an order's delivered units is
    // visibly distinct from a never-fulfilled order, instead of both
    // collapsing to STATUS_SUBMITTED.
    public const STATUS_RETURNED = 'returned';

    public const TERMINAL_STATUSES = [self::STATUS_COMPLETED, self::STATUS_CANCELLED];

    public const IN_PROGRESS_STATUSES = [self::STATUS_PARTIAL, self::STATUS_PROCESSING, self::STATUS_RETURNED];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'updated_at' => 'datetime',
            'completed_at' => 'datetime',
            'customer_received_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(PurchaseOrderAudit::class)->orderByDesc('created_at');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(ProductReturn::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(OrderFollowUp::class);
    }

    public function getTotalAttribute(): string
    {
        return (string) $this->items->sum(fn ($item) => $item->line_total ?? 0);
    }

    public function getPrimaryItemAttribute(): ?PurchaseOrderItem
    {
        return $this->items->first();
    }

    public function getIsAwaitingFulfillmentAttribute(): bool
    {
        return ! in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function getBalanceUnitsAttribute(): int
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return 0;
        }

        return (int) $this->items->sum(fn ($item) => $item->pending_quantity);
    }

    /**
     * Ports update_order_delivery_status() from
     * app/purchase_orders/purchase_order_routes.py -- derives status
     * purely from quantities, no explicit state machine. Mutates in
     * place; caller is responsible for save().
     */
    public function updateDeliveryStatus(): void
    {
        if ($this->items->isEmpty()) {
            $this->status = self::STATUS_SUBMITTED;

            return;
        }

        $totalOrdered = (int) $this->items->sum('quantity');
        $totalDelivered = (int) $this->items->sum(fn ($item) => $item->delivered_quantity ?? 0);

        if ($totalDelivered <= 0) {
            $this->completed_at = null;
            // Nothing is delivered any more, so any earlier "customer
            // confirmed receipt" no longer holds.
            $this->customer_received_at = null;
            $this->status = $this->hasProcessedReturn() ? self::STATUS_RETURNED : self::STATUS_SUBMITTED;
        } elseif ($totalDelivered < $totalOrdered) {
            $this->status = self::STATUS_PARTIAL;
            $this->completed_at = null;
        } else {
            // Delivery settlement and order completion are separate business
            // actions. Once every unit is delivered, staff can review the
            // result and explicitly close the order.
            $this->status = self::STATUS_PROCESSING;
            $this->completed_at = null;
        }
    }

    /**
     * Whether a return for this order has ever been approved (or fully
     * received) -- used to tell "nothing delivered yet" apart from
     * "everything delivered was returned" when totalDelivered hits zero.
     */
    private function hasProcessedReturn(): bool
    {
        return $this->returns()
            ->whereIn('status', [ProductReturn::STATUS_APPROVED, ProductReturn::STATUS_RECEIVED])
            ->exists();
    }
}
