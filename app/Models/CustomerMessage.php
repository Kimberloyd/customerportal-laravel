<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function latestReply(): HasOne
    {
        return $this->hasOne(CustomerMessage::class, 'parent_id')->latestOfMany();
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

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    public function isFacebookMessenger(): bool
    {
        return $this->channel === 'facebook_messenger';
    }

    public function conversationName(): string
    {
        if ($this->customer) {
            return $this->customer->company_name;
        }
        if ($this->external_sender_name) {
            return $this->external_sender_name;
        }
        if ($this->external_sender_id) {
            return 'Facebook customer · '.substr($this->external_sender_id, -8);
        }

        return 'Unassigned customer';
    }

    public function publicLinkIsActive(?CarbonImmutable $now = null): bool
    {
        $now = $now ?? CarbonImmutable::now();

        return $this->isRoot()
            && $this->channel === 'portal'
            && $this->public_token !== null
            && $this->public_token_expires_at !== null
            && $now->lt($this->public_token_expires_at)
            && $this->public_token_revoked_at === null;
    }
}
