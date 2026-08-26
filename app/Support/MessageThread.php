<?php

namespace App\Support;

use App\Events\CustomerMessageSent;
use App\Models\CustomerMessage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Ports the module-level helper functions of
 * app/messages/message_routes.py that aren't tied to a single route --
 * shared by both the authenticated MessageController and the
 * unauthenticated PublicConversationController.
 */
class MessageThread
{
    public static function recipientSenderType(?User $user = null): string
    {
        $user = $user ?? Auth::user();

        return $user?->role === 'customer' ? 'company' : 'customer';
    }

    /**
     * @return Collection<int, CustomerMessage>
     */
    public static function threadMessages(CustomerMessage $thread)
    {
        return collect([$thread, ...$thread->replies])
            ->filter(fn (CustomerMessage $message) => filled($message->body))
            ->sortBy('created_at')
            ->values();
    }

    public static function markReceivedMessagesRead($messages, string $senderType): void
    {
        $unread = $messages->filter(fn (CustomerMessage $m) => $m->sender_type === $senderType && ! $m->is_read);

        if ($unread->isEmpty()) {
            return;
        }

        CustomerMessage::whereKey($unread->pluck('id')->all())->update(['is_read' => true]);

        foreach ($unread as $message) {
            $message->is_read = true;
        }
    }

    public static function createReply(CustomerMessage $thread, string $body, string $senderType, ?string $externalMessageId = null): CustomerMessage
    {
        $now = now();

        $reply = CustomerMessage::create([
            'customer_id' => $thread->customer_id,
            'assigned_user_id' => $thread->assigned_user_id,
            'parent_id' => $thread->id,
            'subject' => $thread->subject,
            'body' => $body,
            'sender_type' => $senderType,
            'is_read' => false,
            'status' => 'open',
            'channel' => $thread->channel,
            'external_sender_id' => $thread->external_sender_id,
            'external_page_id' => $thread->external_page_id,
            'external_message_id' => $externalMessageId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $thread->status = 'open';
        $thread->updated_at = $now;
        $thread->save();

        CustomerMessageSent::dispatch($reply->id, $thread->customer_id);

        return $reply;
    }

    public static function unreadCount(): int
    {
        $user = Auth::user();
        if (! $user) {
            return 0;
        }

        $senderType = self::recipientSenderType($user);
        $query = CustomerMessage::where('sender_type', $senderType)->where('is_read', false);

        if ($user->role === 'customer') {
            $customer = CustomerScope::forCurrentUser(required: false);
            if (! $customer) {
                return 0;
            }
            $query->where('customer_id', $customer->id);
        }

        return $query->count();
    }
}
