<?php

namespace App\Support;

use App\Models\CustomerMessage;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Ports notify_purchase_order_submitted() from
 * app/order_notifications.py -- three independent best-effort
 * notifications, called only when a customer self-creates an order
 * (never for staff-created orders). Each is wrapped separately so one
 * failing never blocks the others, matching Flask exactly.
 *
 * Only createInboxMessage() is a real implementation here -- it's a
 * pure DB write with no external dependency. sendEmail() and
 * sendFacebookSummary() need a live SMTP server and Meta Graph API
 * credentials respectively, neither of which exist in this
 * environment to verify delivery against, so they're left as
 * no-op/log stubs behind a config flag until that infrastructure is
 * actually wired up.
 */
class OrderNotifications
{
    public static function submitted(PurchaseOrder $order): void
    {
        try {
            self::createInboxMessage($order);
        } catch (\Throwable $e) {
            Log::error("Failed to create purchase order inbox message for {$order->po_number}.", ['exception' => $e]);
        }

        try {
            self::sendEmail($order);
        } catch (\Throwable $e) {
            Log::error("Failed to send purchase order email notification for {$order->po_number}.", ['exception' => $e]);
        }

        try {
            self::sendFacebookSummary($order);
        } catch (\Throwable $e) {
            Log::warning("Failed to send Facebook Messenger order summary for {$order->po_number}.", ['exception' => $e]);
        }
    }

    private static function createInboxMessage(PurchaseOrder $order): void
    {
        $now = now();
        $ttlHours = (int) config('services.po_notifications.public_conversation_link_ttl_hours', 720);

        CustomerMessage::create([
            'customer_id' => $order->customer_id,
            'subject' => "Purchase Order {$order->po_number} Submitted",
            'body' => "Thank you. Your purchase order has been successfully submitted.\n\n"
                ."PO Number: {$order->po_number}\n\n"
                ."Our team has been notified and will review your order shortly.\n"
                ."You will receive another update once your order is processed.",
            'sender_type' => 'company',
            'is_read' => false,
            'public_token' => CustomerMessage::hashPublicToken(Str::random(43)),
            'public_token_expires_at' => $now->clone()->addHours($ttlHours),
            'status' => 'open',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private static function sendEmail(PurchaseOrder $order): void
    {
        if (! config('services.po_notifications.email_enabled', false)) {
            Log::info("Email notification skipped for {$order->po_number}: SMTP not configured in this environment.");

            return;
        }

        // Not implemented yet -- no live SMTP server available to
        // verify delivery against. Wire up Mail::send() here once
        // real SMTP config exists.
    }

    private static function sendFacebookSummary(PurchaseOrder $order): void
    {
        if (! config('services.po_notifications.facebook_enabled', false)) {
            Log::info("Facebook Messenger notification skipped for {$order->po_number}: Messenger API not configured in this environment.");

            return;
        }

        // Not implemented yet -- needs a live Meta Graph API token and
        // an already-linked Messenger thread for the customer.
    }
}
