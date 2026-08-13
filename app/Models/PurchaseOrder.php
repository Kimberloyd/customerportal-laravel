<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['po_number', 'customer_id', 'po_file', 'status', 'remarks'])]
class PurchaseOrder extends Model
{
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
}
