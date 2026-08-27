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
 * All four are real: createInboxMessage() is a pure DB write,
 * sendFacebookSummary() notifies every sales agent who has linked their
 * own Facebook account (see MessageController::widgetFacebookLink -- these
 * Facebook contacts are internal staff, not customers) with who ordered
 * and what they ordered, sendEmail() emails the customer's own login
 * address a copy of the same summary, and sendSms() texts that same
 * account's phone number a copy via Semaphore. All four skip silently (and
 * are independently logged) when their feature flag is off or there's
 * nobody to notify.
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
            self::sendSms($order);
        } catch (\Throwable $e) {
            Log::warning("Failed to send purchase order SMS notification for {$order->po_number}.", ['exception' => $e]);
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

    private static function sendSms(PurchaseOrder $order): void
    {
        if (! config('services.po_notifications.sms_enabled', false)) {
            Log::info("SMS notification skipped for {$order->po_number}: feature not enabled in this environment.");

            return;
        }

        $order->loadMissing('customer.user');
        $phone = $order->customer?->user?->phone;

        if (! $phone) {
            Log::info("SMS notification skipped for {$order->po_number}: customer {$order->customer_id} has no phone number on file.");

            return;
        }

        $messageId = SemaphoreSms::send($phone, self::smsSummaryBody($order));

        if ($messageId) {
            Log::info("SMS order summary sent for {$order->po_number} (Semaphore message id {$messageId}).");
        }
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

    /**
     * A single-line variant of facebookSummaryBody() -- SMS is billed per
     * 160-character segment, so items are comma-separated instead of one
     * per line to keep typical orders inside a single credit.
     */
    private static function smsSummaryBody(PurchaseOrder $order): string
    {
        $order->loadMissing(['customer', 'items']);

        $items = $order->items->map(function ($item) {
            $details = collect([$item->generic_name, $item->dosage])->filter()->implode(', ');
            $name = $details === '' ? $item->product_name : "{$item->product_name} ({$details})";

            return "{$name} x{$item->quantity}";
        })->implode(', ');

        return "Order {$order->po_number} submitted for {$order->customer?->company_name}. Items: {$items}. We'll notify you once it's processed.";
    }
}
