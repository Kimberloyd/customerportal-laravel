<?php

namespace App\Support;

use App\Mail\OrderSubmittedMail;
use App\Models\CustomerMessage;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Ports notify_purchase_order_submitted() from
 * app/order_notifications.py -- four independent best-effort
 * notifications, fired for every order regardless of whether a customer
 * or a staff member created it. Each is wrapped separately so one
 * failing never blocks the others, matching Flask exactly.
 *
 * All four are real: createInboxMessage() is a pure DB write,
 * sendFacebookSummary() notifies every sales agent who has linked their
 * own Facebook account (see MessageController::widgetFacebookLink -- these
 * Facebook contacts are internal staff, not customers) with who ordered
 * and what they ordered, sendEmail() emails the customer's own login
 * address a copy of the same summary, and sendSms() texts that same
 * account's phone number a copy via Semaphore. All four skip silently (and
 * are independently logged) when their feature flag is off or there's
 * nobody to notify.
 *
 * Every other order event (updated, fulfillment updated, completed,
 * cancelled) -- again regardless of which side triggered it -- gets at
 * least the inbox notification below, each written alongside a
 * PurchaseOrderNotification audit row so the full lifecycle of an order
 * has a queryable notification trail, not just the creation step.
 */
class OrderNotifications
{
    public static function submitted(PurchaseOrder $order): void
    {
        try {
            self::createInboxMessage($order);
        } catch (\Throwable $e) {
            Log::error("Failed to create purchase order inbox message for {$order->po_number}.", ['exception' => $e]);
            self::record($order, 'inbox', 'failed', note: $e->getMessage());
        }

        try {
            self::sendEmail($order);
        } catch (\Throwable $e) {
            Log::error("Failed to send purchase order email notification for {$order->po_number}.", ['exception' => $e]);
            self::record($order, 'email', 'failed', note: $e->getMessage());
        }

        try {
            self::sendSms($order);
        } catch (\Throwable $e) {
            Log::warning("Failed to send purchase order SMS notification for {$order->po_number}.", ['exception' => $e]);
            self::record($order, 'sms', 'failed', note: $e->getMessage());
        }

        try {
            self::sendFacebookSummary($order);
        } catch (\Throwable $e) {
            Log::warning("Failed to send Facebook Messenger order summary for {$order->po_number}.", ['exception' => $e]);
            self::record($order, 'facebook', 'failed', note: $e->getMessage());
        }
    }

    public static function updated(PurchaseOrder $order, string $summary): void
    {
        self::notifyInboxSafely(
            $order,
            "Order {$order->po_number} Updated",
            "Your order was updated.\n\nPO Number: {$order->po_number}\n\n{$summary}",
            // The bell shows this alone, without the email's "Order was
            // updated" framing around it -- the change summary itself
            // (e.g. "Widget A quantity changed from 5 to 8.") already says
            // specifically what happened, so repeating "Updated" over it
            // would only restate what the reader is about to read anyway.
            $summary,
            'update',
        );
    }

    public static function fulfillmentUpdated(PurchaseOrder $order, string $summary): void
    {
        self::notifyInboxSafely(
            $order,
            "Order {$order->po_number} Delivery Updated",
            "Your order's delivery was updated.\n\nPO Number: {$order->po_number}\n\n{$summary}",
            $summary,
            'fulfillment update',
        );
    }

    public static function completed(PurchaseOrder $order): void
    {
        self::notifyInboxSafely(
            $order,
            "Order {$order->po_number} Completed",
            "Your order has been completed.\n\n"
                ."PO Number: {$order->po_number}\n\n"
                ."All ordered quantities have been delivered.",
            'All ordered quantities have been delivered.',
            'completion',
        );
    }

    public static function cancelled(PurchaseOrder $order): void
    {
        self::notifyInboxSafely(
            $order,
            "Order {$order->po_number} Cancelled",
            "Your order was cancelled.\n\n"
                ."PO Number: {$order->po_number}\n\n"
                ."Contact us if you believe this was a mistake.",
            'Order cancelled. Contact us if this was a mistake.',
            'cancellation',
        );
    }

    /**
     * Shared best-effort wrapper for the single-channel events above --
     * a failure here is logged and recorded exactly like the channels
     * inside submitted(), but never bubbles up and blocks the order
     * action (fulfillment/completion/cancellation) that triggered it.
     */
    private static function notifyInboxSafely(PurchaseOrder $order, string $subject, string $body, string $bellNote, string $eventLabel): void
    {
        try {
            self::notifyInbox($order, $subject, $body, $bellNote);
        } catch (\Throwable $e) {
            Log::error("Failed to create purchase order {$eventLabel} notification for {$order->po_number}.", ['exception' => $e]);
            self::record($order, 'inbox', 'failed', note: $e->getMessage());
        }
    }

    /**
     * Persists an audit trail row for one notification attempt on one
     * channel, alongside the existing Log calls -- so the outcome of every
     * order-submission notification (sent, skipped, or failed) survives
     * past the log retention window.
     */
    private static function record(
        PurchaseOrder $order,
        string $channel,
        string $status,
        ?string $recipient = null,
        ?string $externalReference = null,
        ?string $note = null,
    ): void {
        PurchaseOrderNotification::create([
            'purchase_order_id' => $order->id,
            'channel' => $channel,
            'status' => $status,
            'recipient' => $recipient,
            'external_reference' => $externalReference,
            'note' => $note,
            'created_at' => now(),
        ]);
    }

    private static function createInboxMessage(PurchaseOrder $order): void
    {
        self::notifyInbox(
            $order,
            "Order {$order->po_number} Submitted",
            "Your order was submitted.\n\n"
                ."PO Number: {$order->po_number}\n\n"
                ."Our team has been notified and will review your order shortly.\n"
                ."You will receive another update once your order is processed.",
            'Order received — we\'ll review it shortly.',
        );
    }

    /**
     * Writes one inbox message for the order's customer and the matching
     * PurchaseOrderNotification row -- the shared primitive every
     * customer-facing order event (submitted, updated, fulfillment
     * updated, completed, cancelled) funnels through, so the message
     * shape and the record it leaves behind stay consistent everywhere.
     *
     * $bellNote is deliberately separate from $subject: the email/inbox
     * subject line reads naturally as "Order PO-XXXX Updated", but the
     * notification bell shows entries for several different orders
     * side by side, where a plain state description ("Widget A quantity
     * changed from 5 to 8.") is more scannable than a repeated verb.
     */
    private static function notifyInbox(PurchaseOrder $order, string $subject, string $body, string $bellNote): void
    {
        $now = now();
        $ttlHours = (int) config('services.po_notifications.public_conversation_link_ttl_hours', 720);

        $message = CustomerMessage::create([
            'customer_id' => $order->customer_id,
            'subject' => $subject,
            'body' => $body,
            'sender_type' => 'company',
            // A system-generated receipt, not a staff reply someone is
            // waiting to be seen -- shouldn't badge the Chats/notification
            // icons the way an actual unread message from a person would.
            'is_read' => true,
            'public_token' => CustomerMessage::hashPublicToken(Str::random(43)),
            'public_token_expires_at' => $now->clone()->addHours($ttlHours),
            'status' => 'open',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        self::record($order, 'inbox', 'sent', externalReference: (string) $message->id, note: $bellNote);
    }

    private static function sendEmail(PurchaseOrder $order): void
    {
        if (! config('services.po_notifications.email_enabled', false)) {
            Log::info("Email notification skipped for {$order->po_number}: feature not enabled in this environment.");
            self::record($order, 'email', 'skipped', note: 'feature not enabled in this environment');

            return;
        }

        $order->loadMissing('customer.user');
        $email = $order->customer?->user?->email;

        if (! $email) {
            Log::info("Email notification skipped for {$order->po_number}: customer {$order->customer_id} has no linked login email.");
            self::record($order, 'email', 'skipped', note: 'customer has no linked login email');

            return;
        }

        Mail::to($email)->send(new OrderSubmittedMail($order));
        self::record($order, 'email', 'sent', recipient: $email);
    }

    private static function sendSms(PurchaseOrder $order): void
    {
        if (! config('services.po_notifications.sms_enabled', false)) {
            Log::info("SMS notification skipped for {$order->po_number}: feature not enabled in this environment.");
            self::record($order, 'sms', 'skipped', note: 'feature not enabled in this environment');

            return;
        }

        $order->loadMissing('customer.user');
        $phone = $order->customer?->user?->phone;

        if (! $phone) {
            Log::info("SMS notification skipped for {$order->po_number}: customer {$order->customer_id} has no phone number on file.");
            self::record($order, 'sms', 'skipped', note: 'customer has no phone number on file');

            return;
        }

        $messageId = SemaphoreSms::send($phone, self::smsSummaryBody($order));

        if ($messageId) {
            Log::info("SMS order summary sent for {$order->po_number} (Semaphore message id {$messageId}).");
            self::record($order, 'sms', 'sent', recipient: $phone, externalReference: (string) $messageId);
        } else {
            self::record($order, 'sms', 'failed', recipient: $phone, note: 'Semaphore did not return a message id');
        }
    }

    private static function sendFacebookSummary(PurchaseOrder $order): void
    {
        if (! config('services.po_notifications.facebook_enabled', false)) {
            Log::info("Facebook Messenger notification skipped for {$order->po_number}: feature not enabled in this environment.");
            self::record($order, 'facebook', 'skipped', note: 'feature not enabled in this environment');

            return;
        }

        $threads = CustomerMessage::whereNull('parent_id')
            ->where('channel', 'facebook_messenger')
            ->whereNotNull('assigned_user_id')
            ->where('status', '!=', 'closed')
            ->get();

        if ($threads->isEmpty()) {
            Log::info("Facebook Messenger notification skipped for {$order->po_number}: no sales agent has a linked Facebook thread.");
            self::record($order, 'facebook', 'skipped', note: 'no sales agent has a linked Facebook thread');

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
                self::record($order, 'facebook', 'sent', recipient: (string) $thread->id, externalReference: $externalMessageId);
            } catch (\Throwable $e) {
                Log::warning("Failed to send Facebook Messenger order summary to thread {$thread->id} for {$order->po_number}.", ['exception' => $e]);
                self::record($order, 'facebook', 'failed', recipient: (string) $thread->id, note: $e->getMessage());
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

    /**
     * A numbered, multi-line variant of facebookSummaryBody() -- worth the
     * extra SMS segment(s) it can cost over a comma-run single line,
     * because a customer scanning a text on their phone can pick out "item
     * 3" from a numbered list far faster than from one dense sentence.
     */
    private static function smsSummaryBody(PurchaseOrder $order): string
    {
        $order->loadMissing(['customer', 'items']);

        $lines = [
            "Order {$order->po_number} submitted for {$order->customer?->company_name}.",
            '',
            'Items:',
        ];

        foreach ($order->items as $index => $item) {
            $details = collect([$item->generic_name, $item->dosage])->filter()->implode(', ');
            $name = $details === '' ? $item->product_name : "{$item->product_name} ({$details})";

            $lines[] = ($index + 1).". {$name} x{$item->quantity}";
        }

        $lines[] = '';
        $lines[] = "We'll text you again once it's processed.";

        return implode("\n", $lines);
    }
}
