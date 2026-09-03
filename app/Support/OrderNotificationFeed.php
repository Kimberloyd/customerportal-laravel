<?php

namespace App\Support;

use App\Models\PurchaseOrderNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Feeds the header notification bell from purchase_order_notifications --
 * mirrors MessageThread::unreadCount()'s role-based scoping (customers see
 * only their own orders' notifications, staff see every order's), but reads
 * the 'portal' channel only: that's the one row OrderNotifications writes
 * per order event regardless of which other channels (email/sms/facebook)
 * also fired for it, so showing only it avoids surfacing the same event
 * three or four times in the same list.
 */
class OrderNotificationFeed
{
    public static function recent(int $limit = 20): Collection
    {
        return self::scopedQuery()
            ?->with([
                'purchaseOrder:id,po_number,customer_id',
                'purchaseOrder.customer:id,company_name',
            ])
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ?? new Collection;
    }

    /**
     * The persisted note is the customer-facing audit message. Staff need
     * operational context instead: who the order belongs to and what action
     * the company should take. Presenting that distinction here also updates
     * existing notification rows without rewriting their audit history.
     */
    public static function messageForCurrentUser(PurchaseOrderNotification $notification): ?string
    {
        $note = $notification->note;
        $user = Auth::user();

        if (! $user || $user->role === 'customer' || ! $note) {
            return $note;
        }

        $customerName = $notification->purchaseOrder?->customer?->company_name;
        if (! $customerName) {
            return $note;
        }

        if (str_starts_with($note, 'Order received')) {
            return "New order from {$customerName} — ready for review.";
        }

        if ($note === 'All ordered quantities have been delivered.') {
            return "Order for {$customerName} is complete.";
        }

        if (str_starts_with($note, 'Order cancelled.')) {
            return "Order for {$customerName} was cancelled.";
        }

        if (str_starts_with($note, 'Return requested')) {
            return "Return request from {$customerName} needs review.";
        }

        return "{$customerName}: {$note}";
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
            ->where('channel', 'portal')
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
