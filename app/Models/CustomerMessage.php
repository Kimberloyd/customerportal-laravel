<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'customer_id', 'subject', 'body', 'parent_id', 'sender_type',
    'is_read', 'public_token', 'public_token_expires_at',
    'public_token_revoked_at', 'status', 'error_message', 'channel',
    'external_sender_id', 'external_sender_name',
    'external_sender_profile_checked_at', 'external_page_id',
    'external_message_id', 'sent_at', 'created_at', 'updated_at',
])]
class CustomerMessage extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'public_token_expires_at' => 'datetime',
            'public_token_revoked_at' => 'datetime',
            'external_sender_profile_checked_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(CustomerMessage::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(CustomerMessage::class, 'parent_id');
    }

    public function replyAttempts(): HasMany
    {
        return $this->hasMany(PublicConversationReplyAttempt::class);
    }

    /**
     * Matches Flask's hash_public_token() -- the raw bearer token is
     * never stored, only this digest, same treatment as a password.
     */
    public static function hashPublicToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }
}
