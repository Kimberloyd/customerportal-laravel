<?php

namespace App\Events;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $queue = 'broadcasts';

    public function __construct(
        public readonly int $orderId,
        public readonly string $change,
        public readonly ?int $previousCustomerId = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $order = PurchaseOrder::query()->find($this->orderId);
        $customerIds = array_values(array_unique(array_filter([
            $order?->customer_id,
            $this->previousCustomerId,
        ])));

        $recipientIds = User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($customerIds): void {
                $query->whereIn('role', ['admin', 'employee']);

                if ($customerIds !== []) {
                    $query->orWhereHas('customer', fn ($customer) => $customer->whereIn('id', $customerIds));
                }
            })
            ->pluck('id');

        return $recipientIds
            ->map(fn (int $userId) => new PrivateChannel("users.{$userId}"))
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'purchase-order.changed';
    }

    /**
     * @return array{order_id: int, change: string, status: ?string, updated_at: ?string}
     */
    public function broadcastWith(): array
    {
        $order = PurchaseOrder::query()->find($this->orderId);

        return [
            'order_id' => $this->orderId,
            'change' => $this->change,
            'status' => $order?->status,
            'updated_at' => $order?->updated_at?->toIso8601String(),
        ];
    }
}
