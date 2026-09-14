<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'context', 'entity_key', 'label'])]
class SearchSelection extends Model
{
    public const UPDATED_AT = null;

    public const CONTEXT_CUSTOMER = 'customer';

    public const CONTEXT_PRODUCT = 'product';

    public const CONTEXT_MESSAGE_ACCOUNT = 'message_account';

    public const CONTEXT_ORDER_SEARCH = 'order_search';

    public const CONTEXTS = [
        self::CONTEXT_CUSTOMER,
        self::CONTEXT_PRODUCT,
        self::CONTEXT_MESSAGE_ACCOUNT,
        self::CONTEXT_ORDER_SEARCH,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
