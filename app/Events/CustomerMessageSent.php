<?php

namespace App\Events;

use App\Models\CustomerMessage;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $queue = 'broadcasts';

    public function __construct(
        public readonly int $messageId,
        public readonly ?int $customerId,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $recipientIds = User::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereIn('role', ['admin', 'employee']);

                if ($this->customerId !== null) {
                    $query->orWhereHas('customer', fn ($customer) => $customer->whereKey($this->customerId));
                }
            })
            ->pluck('id');

        return $recipientIds
            ->map(fn (int $userId) => new PrivateChannel("users.{$userId}"))
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'customer-message.created';
    }

    /**
     * @return array{message_id: int, thread_id: ?int, customer_id: ?int, assigned_user_id: ?int, sender_type: ?string, body: ?string, created_at: ?string}
     */
    public function broadcastWith(): array
    {
        $message = CustomerMessage::find($this->messageId);

        return [
            'message_id' => $this->messageId,
            'thread_id' => $message ? ($message->parent_id ?? $message->id) : null,
            'customer_id' => $this->customerId,
            'assigned_user_id' => $message?->assigned_user_id,
            'sender_type' => $message?->sender_type,
            'body' => $message?->body,
            'created_at' => $message?->created_at?->toIso8601String(),
        ];
    }
}
