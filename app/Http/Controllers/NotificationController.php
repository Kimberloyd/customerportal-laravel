<?php

namespace App\Http\Controllers;

use App\Support\OrderNotificationFeed;

class NotificationController extends Controller
{
    public function recent()
    {
        $since = OrderNotificationFeed::unreadSince();
        $notifications = OrderNotificationFeed::recent()->map(fn ($notification) => [
            'id' => $notification->id,
            'note' => OrderNotificationFeed::messageForCurrentUser($notification),
            'created_at' => $notification->created_at?->toIso8601String(),
            'is_unread' => $notification->created_at !== null && $notification->created_at > $since,
            'order_id' => $notification->purchaseOrder?->id,
            'po_number' => $notification->purchaseOrder?->po_number,
        ]);

        return response()->json([
            'notifications' => $notifications,
            'count' => OrderNotificationFeed::recentCount($since),
        ]);
    }

    public function markAllRead()
    {
        OrderNotificationFeed::markAllRead();

        return response()->json(['count' => 0]);
    }
}
