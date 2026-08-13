<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_order_id', 'product_id', 'quantity', 'delivered_quantity',
    'unit_price', 'line_total', 'product_name', 'sku', 'unit', 'description',
])]
class PurchaseOrderItem extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getPendingQuantityAttribute(): int
    {
        return max($this->quantity - ($this->delivered_quantity ?? 0), 0);
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->product_name) {
            return $this->product_name;
        }

        return $this->product?->product_name ?? 'Unknown product';
    }
}
