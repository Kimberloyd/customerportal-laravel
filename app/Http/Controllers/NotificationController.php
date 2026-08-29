<?php

namespace App\Http\Controllers;

use App\Support\OrderNotificationFeed;

class NotificationController extends Controller
{
    public function recent()
    {
        $notifications = OrderNotificationFeed::recent()->map(fn ($notification) => [
            'id' => $notification->id,
            'note' => $notification->note,
            'created_at' => $notification->created_at?->toIso8601String(),
            'order_id' => $notification->purchaseOrder?->id,
            'po_number' => $notification->purchaseOrder?->po_number,
        ]);

        return response()->json([
            'notifications' => $notifications,
            'count' => OrderNotificationFeed::recentCount(),
        ]);
    }

    public function markAllRead()
    {
        OrderNotificationFeed::markAllRead();

        return response()->json(['count' => 0]);
    }
}
