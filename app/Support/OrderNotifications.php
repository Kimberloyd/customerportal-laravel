<?php

namespace App\Support;

use App\Mail\OrderSubmittedMail;
use App\Models\CustomerMessage;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Ports notify_purchase_order_submitted() from
 * app/order_notifications.py -- three independent best-effort
 * notifications, called only when a customer self-creates an order
 * (never for staff-created orders). Each is wrapped separately so one
 * failing never blocks the others, matching Flask exactly.
 *
 * All three are real: createInboxMessage() is a pure DB write,
 * sendFacebookSummary() notifies every sales agent who has linked their
 * own Facebook account (see MessageController::widgetFacebookLink -- these
 * Facebook contacts are internal staff, not customers) with who ordered
 * and what they ordered, and sendEmail() emails the customer's own login
 * address a copy of the same summary. All three skip silently (and are
 * independently logged) when their feature flag is off or there's nobody
 * to notify.
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
            'subject' => "Order {$order->po_number} Submitted",
            'body' => "Your order was submitted.\n\n"
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
            Log::info("Email notification skipped for {$order->po_number}: feature not enabled in this environment.");

            return;
        }

        $order->loadMissing('customer.user');
        $email = $order->customer?->user?->email;

        if (! $email) {
            Log::info("Email notification skipped for {$order->po_number}: customer {$order->customer_id} has no linked login email.");

            return;
        }

        Mail::to($email)->send(new OrderSubmittedMail($order));
    }

    private static function sendFacebookSummary(PurchaseOrder $order): void
    {
        if (! config('services.po_notifications.facebook_enabled', false)) {
            Log::info("Facebook Messenger notification skipped for {$order->po_number}: feature not enabled in this environment.");

            return;
        }

        $threads = CustomerMessage::whereNull('parent_id')
            ->where('channel', 'facebook_messenger')
            ->whereNotNull('assigned_user_id')
            ->where('status', '!=', 'closed')
            ->get();

        if ($threads->isEmpty()) {
            Log::info("Facebook Messenger notification skipped for {$order->po_number}: no sales agent has a linked Facebook thread.");

            return;
        }

        $body = self::facebookSummaryBody($order);

        // Each agent's send is independent -- one failing (e.g. their
        // 24-hour messaging window has lapsed) shouldn't stop the others
        // from being notified.
        foreach ($threads as $thread) {
            try {
                $externalMessageId = FacebookMessenger::sendReply($thread, $body);
                MessageThread::createReply($thread, $body, 'company', $externalMessageId);
            } catch (\Throwable $e) {
                Log::warning("Failed to send Facebook Messenger order summary to thread {$thread->id} for {$order->po_number}.", ['exception' => $e]);
            }
        }
    }

    /**
     * Every field carries its own label and the item list is numbered so a
     * sales agent can scan straight to what they need (or reference "item
     * 2" back to a customer) without parsing a paragraph.
     */
    private static function facebookSummaryBody(PurchaseOrder $order): string
    {
        $order->loadMissing(['customer', 'items']);

        $lines = [
            "New order — PO {$order->po_number}",
            "Customer: {$order->customer?->company_name}",
            '',
            'Items:',
        ];

        foreach ($order->items as $index => $item) {
            // Generic name and variant (dosage/strength) are both optional
            // per product -- shown together in parentheses only when at
            // least one is present, so a plain product never gets stray "()".
            $details = collect([$item->generic_name, $item->dosage])->filter()->implode(', ');
            $name = $details === '' ? $item->product_name : "{$item->product_name} ({$details})";

            $lines[] = ($index + 1).". {$name} — {$item->quantity} {$item->unit}";
        }

        return implode("\n", $lines);
    }
}
