<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_return_id', 'purchase_order_item_id', 'quantity'])]
class ProductReturnItem extends Model
{
    public $timestamps = false;

    public function productReturn(): BelongsTo
    {
        return $this->belongsTo(ProductReturn::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }
}
