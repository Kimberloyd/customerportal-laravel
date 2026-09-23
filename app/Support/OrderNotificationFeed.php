<?php

namespace App\Support;

use App\Models\NotificationRead;
use App\Models\PurchaseOrderNotification;
use App\Models\User;
use Carbon\CarbonInterface;
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
        $userId = Auth::id();

        return self::scopedQuery()
            ?->with([
                'purchaseOrder:id,public_id,po_number,customer_id',
                'purchaseOrder.customer:id,company_name',
                // Constrained to this viewer: each notification row can be
                // shared across every staff member (recipient_user_id null),
                // so whether it's read has to be checked per viewer, not
                // read off the row itself.
                'reads' => fn ($query) => $query->where('user_id', $userId),
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
            return "New order from {$customerName} — ready for fulfillment.";
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
    public static function unreadSince(): CarbonInterface
    {
        return Auth::user()?->notifications_read_at ?? now()->subHours(24);
    }

    public static function recentCount(?CarbonInterface $since = null): int
    {
        $user = Auth::user();
        $query = self::scopedQuery()?->where('created_at', '>', $since ?? self::unreadSince());

        if (! $query || ! $user) {
            return 0;
        }

        return $query->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id))->count();
    }

    public static function markAllRead(): void
    {
        Auth::user()?->update(['notifications_read_at' => now()]);
    }

    /**
     * The explicit "just this one" override the watermark can't express on
     * its own -- scoped through the same visibility rules as recent()/
     * recentCount(), so a user can't mark a notification read that they
     * couldn't otherwise see.
     */
    public static function markOneRead(int $notificationId): bool
    {
        $user = Auth::user();
        $notification = $user ? self::scopedQuery()?->whereKey($notificationId)->first() : null;

        if (! $notification) {
            return false;
        }

        NotificationRead::firstOrCreate(
            ['purchase_order_notification_id' => $notification->id, 'user_id' => $user->id],
            ['read_at' => now()],
        );

        return true;
    }

    private static function scopedQuery()
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        $query = PurchaseOrderNotification::query()
            ->where('channel', 'portal')
            ->where('status', 'sent')
            ->where(fn ($query) => $query
                ->whereNull('recipient_user_id')
                ->orWhere('recipient_user_id', $user->id));

        if ($user->role === 'customer') {
            $customer = CustomerScope::forCurrentUser(required: false);
            if (! $customer) {
                return null;
            }
            $query->whereHas('purchaseOrder', fn ($q) => $q->where('customer_id', $customer->id));
        } elseif ($user->role === User::ROLE_AGENT) {
            $query->whereHas('purchaseOrder', fn ($q) => $q
                ->whereIn('customer_id', CustomerAccess::customerIdsFor($user)));
        }

        return $query;
    }
}
