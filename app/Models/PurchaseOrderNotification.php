<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_order_id', 'channel', 'status', 'event_key', 'recipient_user_id',
    'follow_up_id', 'level', 'dedupe_key', 'recipient', 'external_reference',
    'note', 'created_at',
])]
class PurchaseOrderNotification extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function followUp(): BelongsTo
    {
        return $this->belongsTo(OrderFollowUp::class, 'follow_up_id');
    }
}
