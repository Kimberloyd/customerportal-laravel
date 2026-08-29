<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'po_number', 'customer_id', 'po_file', 'status', 'remarks',
    'submitted_at', 'updated_at', 'completed_at',
])]
class PurchaseOrder extends Model
{
    public $timestamps = false;

    // Matches app/models.py's ORDER_STATUS_* / ORDER_TERMINAL_STATUSES /
    // ORDER_IN_PROGRESS_STATUSES in the Flask app -- single source of
    // truth for this domain lives there until this table's routes are
    // actually built in a later phase; kept identical here so nothing
    // drifts in the meantime.
    public const STATUS_SUBMITTED = 'submitted';
    // Not part of the Flask original -- set the first time a staff member
    // (never the customer) opens a submitted order, so the team can see
    // at a glance which new orders someone has already started looking
    // at. Purely informational: it carries no other behavior difference
    // from "submitted" and updateDeliveryStatus() treats it the same way.
    public const STATUS_REVIEWING = 'reviewing';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const TERMINAL_STATUSES = [self::STATUS_COMPLETED, self::STATUS_CANCELLED];
    public const IN_PROGRESS_STATUSES = [self::STATUS_PARTIAL, self::STATUS_PROCESSING];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'updated_at' => 'datetime',
            'completed_at' => 'datetime',
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
            // Don't clobber "reviewing" back to "submitted" on an
            // unrelated edit (e.g. changing quantities/remarks) made
            // before fulfillment starts -- that would erase the "someone
            // already looked at this" signal for no real reason.
            if ($this->status !== self::STATUS_REVIEWING) {
                $this->status = self::STATUS_SUBMITTED;
            }
        } elseif ($totalDelivered < $totalOrdered) {
            $this->status = self::STATUS_PARTIAL;
        } else {
            $this->status = self::STATUS_COMPLETED;
            if ($this->completed_at === null) {
                $this->completed_at = now();
            }
        }
    }
}
