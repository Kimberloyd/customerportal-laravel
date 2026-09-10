<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'purchase_order_id', 'product_return_id', 'kind', 'cycle', 'level', 'status',
    'triggered_at', 'next_due_at', 'last_dispatched_at', 'resolved_at',
    'attempt_count', 'last_error_at',
])]
class OrderFollowUp extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_DISPATCHING = 'dispatching';

    public const STATUS_ESCALATED = 'escalated';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_PAUSED = 'paused';

    protected function casts(): array
    {
        return [
            'triggered_at' => 'datetime',
            'next_due_at' => 'datetime',
            'last_dispatched_at' => 'datetime',
            'resolved_at' => 'datetime',
            'last_error_at' => 'datetime',
            'cycle' => 'integer',
            'attempt_count' => 'integer',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function productReturn(): BelongsTo
    {
        return $this->belongsTo(ProductReturn::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(PurchaseOrderNotification::class, 'follow_up_id');
    }
}
