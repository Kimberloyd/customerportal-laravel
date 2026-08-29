<?php

namespace App\Support;

use App\Models\PurchaseOrderNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Feeds the header notification bell from purchase_order_notifications --
 * mirrors MessageThread::unreadCount()'s role-based scoping (customers see
 * only their own orders' notifications, staff see every order's), but reads
 * the 'inbox' channel only: that's the one row OrderNotifications writes
 * per order event regardless of which other channels (email/sms/facebook)
 * also fired for it, so showing only it avoids surfacing the same event
 * three or four times in the same list.
 */
class OrderNotificationFeed
{
    public static function recent(int $limit = 20): Collection
    {
        return self::scopedQuery()
            ?->with('purchaseOrder:id,po_number,customer_id')
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ?? new Collection();
    }

    /**
     * Unread count, gated by users.notifications_read_at -- "Mark all as
     * read" just bumps that timestamp to now(), so this naturally drops
     * to zero without needing to touch every notification row. An
     * account that has never read its notifications (null) falls back
     * to the last 24 hours, so a long-lived account isn't hit with its
     * entire order history as "unread" the first time it opens the bell.
     */
    public static function recentCount(): int
    {
        $user = Auth::user();
        $since = $user?->notifications_read_at ?? now()->subHours(24);

        return self::scopedQuery()
            ?->where('created_at', '>', $since)
            ->count()
            ?? 0;
    }

    public static function markAllRead(): void
    {
        Auth::user()?->update(['notifications_read_at' => now()]);
    }

    private static function scopedQuery()
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        $query = PurchaseOrderNotification::query()
            ->where('channel', 'inbox')
            ->where('status', 'sent');

        if ($user->role === 'customer') {
            $customer = CustomerScope::forCurrentUser(required: false);
            if (! $customer) {
                return null;
            }
            $query->whereHas('purchaseOrder', fn ($q) => $q->where('customer_id', $customer->id));
        }

        return $query;
    }
}
