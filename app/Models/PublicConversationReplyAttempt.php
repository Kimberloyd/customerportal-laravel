<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_message_id', 'client_key'])]
class PublicConversationReplyAttempt extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['attempted_at' => 'datetime'];
    }

    public function customerMessage(): BelongsTo
    {
        return $this->belongsTo(CustomerMessage::class);
    }
}
