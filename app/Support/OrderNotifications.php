<?php

namespace App\Support;

use App\Jobs\SendOrderNotifications;
use App\Mail\OrderSubmittedMail;
use App\Models\AppSetting;
use App\Models\CustomerMessage;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Ports notify_purchase_order_submitted() from
 * app/order_notifications.py -- four independent best-effort
 * notifications, fired for every order regardless of whether a customer
 * or a staff member created it. Each is wrapped separately so one
 * failing never blocks the others, matching Flask exactly.
 *
 * All four are real: notifyPortal() writes the notification
 * bell record without creating a chat message,
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
 * cancelled, received) -- again regardless of which side triggered it --
 * gets both a portal notification and an SMS attempt, each written as a
 * PurchaseOrderNotification audit row so the full lifecycle of an order
 * has a queryable notification trail, not just the creation step.
 */
class OrderNotifications
{
    /** AppSetting key holding the administrator's runtime SMS on/off override. */
    public const SMS_ENABLED_SETTING = 'po_notifications.sms_enabled';

    public static function submitted(PurchaseOrder $order): void
    {
        self::notifyPortalSafely($order, 'Order received. We\'ll prepare it for fulfillment.', 'submission');
        self::queue($order, 'submitted');
    }

    public static function updated(PurchaseOrder $order, string $summary): void
    {
        self::notifyPortalSafely($order, $summary, 'update');
        self::queue($order, 'updated');
    }

    public static function fulfillmentUpdated(PurchaseOrder $order, string $summary): void
    {
        self::notifyPortalSafely($order, $summary, 'fulfillment update');
        self::queue($order, 'fulfillment-updated');
    }

    public static function completed(PurchaseOrder $order): void
    {
        self::notifyPortalSafely($order, 'All ordered quantities have been delivered.', 'completion');
        self::queue($order, 'completed');
    }

    public static function cancelled(PurchaseOrder $order): void
    {
        self::notifyPortalSafely($order, 'Order cancelled. Contact us if this was a mistake.', 'cancellation');
        self::queue($order, 'cancelled');
    }

    public static function received(PurchaseOrder $order): void
    {
        self::notifyPortalSafely($order, 'Thank you for confirming receipt.', 'receipt confirmation');
        self::queue($order, 'received');
    }

    public static function returnRequested(PurchaseOrder $order): void
    {
        self::notifyPortalSafely($order, 'Return requested. Staff review is needed.', 'return request');
    }

    public static function returnUpdated(PurchaseOrder $order, string $status): void
    {
        [$note] = self::returnCopy($order, $status);
        self::notifyPortalSafely($order, $note, 'return update');
        self::queue($order, 'return-updated', $status);
    }

    public static function deliver(PurchaseOrder $order, string $event, ?string $context = null): void
    {
        match ($event) {
            'submitted' => self::deliverSubmitted($order),
            'updated' => self::deliverUpdated($order),
            'fulfillment-updated' => self::deliverFulfillmentUpdated($order),
            'completed' => self::deliverCompleted($order),
            'cancelled' => self::deliverCancelled($order),
            'received' => self::deliverReceived($order),
            'return-updated' => self::deliverReturnUpdated($order, (string) $context),
            default => throw new \InvalidArgumentException("Unsupported order notification event: {$event}"),
        };
    }

    private static function queue(PurchaseOrder $order, string $event, ?string $context = null): void
    {
        SendOrderNotifications::dispatch($order->id, $event, $context);
    }

    private static function deliverSubmitted(PurchaseOrder $order): void
    {
        try {
            self::sendEmail($order);
        } catch (\Throwable $e) {
            Log::error("Failed to send purchase order email notification for {$order->po_number}.", ['exception' => $e]);
            self::record($order, 'email', 'failed', note: $e->getMessage());
        }

        try {
            self::sendSms($order, self::smsSummaryBody($order));
        } catch (\Throwable $e) {
            Log::warning("Failed to send purchase order SMS notification for {$order->po_number}.", ['exception' => $e]);
            self::record($order, 'sms', 'failed', note: $e->getMessage());
        }

        self::notifyPushSafely($order, 'submission', "Order {$order->po_number} received. We'll prepare it for fulfillment.");

        try {
            self::sendFacebookSummary($order);
        } catch (\Throwable $e) {
            Log::warning("Failed to send Facebook Messenger order summary for {$order->po_number}.", ['exception' => $e]);
            self::record($order, 'facebook', 'failed', note: $e->getMessage());
        }

        try {
            self::sendAgentSms($order);
        } catch (\Throwable $e) {
            Log::warning("Failed to send agent SMS order summary for {$order->po_number}.", ['exception' => $e]);
            self::record($order, 'agent_sms', 'failed', note: $e->getMessage());
        }
    }

    private static function deliverUpdated(PurchaseOrder $order): void
    {
        self::notifyCustomerSafely(
            $order,
            'update',
            "Order {$order->po_number} was updated. View your portal for the latest details.",
        );
    }

    private static function deliverFulfillmentUpdated(PurchaseOrder $order): void
    {
        self::notifyCustomerSafely(
            $order,
            'fulfillment update',
            "Order {$order->po_number} delivery was updated. View your portal for the latest details.",
        );
    }

    private static function deliverCompleted(PurchaseOrder $order): void
    {
        self::notifyCustomerSafely(
            $order,
            'completion',
            "Order {$order->po_number} is complete. All items have been delivered.",
        );
    }

    private static function deliverCancelled(PurchaseOrder $order): void
    {
        self::notifyCustomerSafely(
            $order,
            'cancellation',
            "Order {$order->po_number} was cancelled. Contact us if this was a mistake.",
        );
    }

    private static function deliverReceived(PurchaseOrder $order): void
    {
        self::notifyCustomerSafely(
            $order,
            'receipt confirmation',
            "Order {$order->po_number} has been marked as received. Thank you.",
        );
    }

    private static function deliverReturnUpdated(PurchaseOrder $order, string $status): void
    {
        [, $eventLabel, $smsBody] = self::returnCopy($order, $status);

        self::notifyCustomerSafely($order, $eventLabel, $smsBody);
    }

    private static function returnCopy(PurchaseOrder $order, string $status): array
    {
        return match ($status) {
            'approved' => [
                'Your return request was approved. Our team will coordinate the return.',
                'return approval',
                "Your return request for order {$order->po_number} was approved. Our team will coordinate the return.",
            ],
            'rejected' => [
                'Your return request was not approved. See the return details for the reason.',
                'return decision',
                "Your return request for order {$order->po_number} was not approved. See the portal for details.",
            ],
            'received' => [
                'Your returned products were received by our team.',
                'return receipt',
                "Returned products for order {$order->po_number} were received by our team.",
            ],
        };
    }

    /**
     * Shared best-effort wrapper for the single-channel events above --
     * a failure here is logged and recorded exactly like the channels
     * inside submitted(), but never bubbles up and blocks the order
     * action (fulfillment/completion/cancellation) that triggered it.
     */
    private static function notifyPortalSafely(
        PurchaseOrder $order,
        string $bellNote,
        string $eventLabel,
    ): void {
        try {
            self::notifyPortal($order, $bellNote);
        } catch (\Throwable $e) {
            Log::error("Failed to create purchase order {$eventLabel} notification for {$order->po_number}.", ['exception' => $e]);
            self::record($order, 'portal', 'failed', note: $e->getMessage());
        }
    }

    /** The customer's phone gets the same wording by text message and by push. */
    private static function notifyCustomerSafely(PurchaseOrder $order, string $eventLabel, string $body): void
    {
        self::notifySmsSafely($order, $eventLabel, $body);
        self::notifyPushSafely($order, $eventLabel, $body);
    }

    private static function notifyPushSafely(PurchaseOrder $order, string $eventLabel, string $body): void
    {
        try {
            self::sendPush($order, $body);
        } catch (\Throwable $e) {
            Log::warning("Failed to send purchase order {$eventLabel} push notification for {$order->po_number}.", ['exception' => $e]);
            self::record($order, 'push', 'failed', note: $e->getMessage());
        }
    }

    private static function notifySmsSafely(PurchaseOrder $order, string $eventLabel, string $smsBody): void
    {
        try {
            self::sendSms($order, $smsBody);
        } catch (\Throwable $e) {
            Log::warning("Failed to send purchase order {$eventLabel} SMS notification for {$order->po_number}.", ['exception' => $e]);
            self::record($order, 'sms', 'failed', note: $e->getMessage());
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

    /**
     * Writes one PurchaseOrderNotification row for the notification bell.
     * It deliberately does not create a CustomerMessage: that model is
     * reserved for actual portal and Facebook chat conversations.
     * This is the shared primitive every
     * customer-facing order event (submitted, updated, fulfillment
     * updated, completed, cancelled) funnels through.
     */
    private static function notifyPortal(PurchaseOrder $order, string $bellNote): void
    {
        self::record($order, 'portal', 'sent', note: $bellNote);
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

    /**
     * True when SMS sending is on. An administrator's runtime toggle (see
     * AppSetting and SettingsController::updateSms) wins when it has been set;
     * otherwise this is the env-level PO_NOTIFICATIONS_SMS_ENABLED flag exactly
     * as before.
     */
    public static function smsEnabled(): bool
    {
        return AppSetting::boolean(
            self::SMS_ENABLED_SETTING,
            (bool) config('services.po_notifications.sms_enabled', false),
        );
    }

    private static function sendSms(PurchaseOrder $order, string $body): void
    {
        if (! self::smsEnabled()) {
            // Distinguish an admin switching sending off from an environment
            // that never had it on, so the audit trail explains which it was.
            $reason = config('services.po_notifications.sms_enabled', false)
                ? 'sending turned off by an administrator'
                : 'feature not enabled in this environment';

            Log::info("SMS notification skipped for {$order->po_number}: {$reason}.");
            self::record($order, 'sms', 'skipped', note: $reason);

            return;
        }

        $order->loadMissing('customer.user');
        $phone = $order->customer?->user?->phone;

        if (! $phone) {
            Log::info("SMS notification skipped for {$order->po_number}: customer {$order->customer_id} has no phone number on file.");
            self::record($order, 'sms', 'skipped', note: 'customer has no phone number on file');

            return;
        }

        $messageId = SemaphoreSms::send($phone, $body);

        if ($messageId) {
            Log::info("SMS order summary sent for {$order->po_number} (Semaphore message id {$messageId}).");
            self::record($order, 'sms', 'sent', recipient: $phone, externalReference: (string) $messageId);
        } else {
            self::record($order, 'sms', 'failed', recipient: $phone, note: 'Semaphore did not return a message id');
        }
    }

    /**
     * Pushes the customer's login account on every phone signed in to the
     * Android app. Tapping the notification opens the order.
     */
    private static function sendPush(PurchaseOrder $order, string $body): void
    {
        if (! config('services.po_notifications.push_enabled', false)) {
            Log::info("Push notification skipped for {$order->po_number}: feature not enabled in this environment.");
            self::record($order, 'push', 'skipped', note: 'feature not enabled in this environment');

            return;
        }

        if (! FirebaseCloudMessaging::isConfigured()) {
            Log::info("Push notification skipped for {$order->po_number}: Firebase key not configured in this environment.");
            self::record($order, 'push', 'skipped', note: 'Firebase key not configured in this environment');

            return;
        }

        $order->loadMissing('customer.user');
        $user = $order->customer?->user;

        if (! $user || ! $user->is_active) {
            self::record($order, 'push', 'skipped', note: 'customer has no active login account');

            return;
        }

        $tokens = $user->pushTokens;

        if ($tokens->isEmpty()) {
            self::record($order, 'push', 'skipped', recipient: (string) $user->id, note: 'no phone is signed in to the app');

            return;
        }

        foreach ($tokens as $token) {
            $result = FirebaseCloudMessaging::send(
                $token->token,
                'Customer Portal',
                $body,
                ['url' => route('purchase-orders.show', $order->public_id, absolute: false)],
            );

            if ($result === FirebaseCloudMessaging::UNREGISTERED) {
                $token->delete();
                self::record($order, 'push', 'skipped', recipient: (string) $user->id, note: 'app was uninstalled from that phone; token removed');

                continue;
            }

            self::record(
                $order,
                'push',
                $result === FirebaseCloudMessaging::SENT ? 'sent' : 'failed',
                recipient: (string) $user->id,
                note: $result === FirebaseCloudMessaging::SENT ? null : 'Firebase did not accept the message',
            );
        }
    }

    private static function sendFacebookSummary(PurchaseOrder $order): void
    {
        if (! config('services.po_notifications.facebook_enabled', false)) {
            Log::info("Facebook Messenger notification skipped for {$order->po_number}: feature not enabled in this environment.");
            self::record($order, 'facebook', 'skipped', note: 'feature not enabled in this environment');

            return;
        }

        if (! FacebookMessenger::isConfigured()) {
            Log::info("Facebook Messenger notification skipped for {$order->po_number}: Messenger API not configured in this environment.");
            self::record($order, 'facebook', 'skipped', note: 'Messenger API not configured in this environment');

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
     * Texts the same sales agents sendFacebookSummary() messages on
     * Messenger, so an order summary reaches them even if their 24-hour
     * Messenger window has lapsed or they haven't linked a Facebook thread
     * at all. Reuses the same "which threads are agent-linked" query, but
     * resolves each thread's assignedUser and sends by phone rather than
     * PSID -- deduped, since one agent can have more than one linked
     * thread and shouldn't be texted the same summary twice.
     */
    private static function sendAgentSms(PurchaseOrder $order): void
    {
        if (! config('services.po_notifications.agent_sms_enabled', false)) {
            Log::info("Agent SMS notification skipped for {$order->po_number}: feature not enabled in this environment.");
            self::record($order, 'agent_sms', 'skipped', note: 'feature not enabled in this environment');

            return;
        }

        $agentIds = CustomerMessage::whereNull('parent_id')
            ->where('channel', 'facebook_messenger')
            ->whereNotNull('assigned_user_id')
            ->where('status', '!=', 'closed')
            ->distinct()
            ->pluck('assigned_user_id');

        $agents = User::whereIn('id', $agentIds)->get();

        if ($agents->isEmpty()) {
            Log::info("Agent SMS notification skipped for {$order->po_number}: no sales agent has a linked Facebook thread.");
            self::record($order, 'agent_sms', 'skipped', note: 'no sales agent has a linked Facebook thread');

            return;
        }

        $body = self::agentSmsSummaryBody($order);

        // Each agent's send is independent -- one missing/invalid phone
        // number shouldn't stop the others from being notified.
        foreach ($agents as $agent) {
            if (! $agent->phone) {
                Log::info("Agent SMS notification skipped for {$order->po_number}: agent {$agent->id} has no phone number on file.");
                self::record($order, 'agent_sms', 'skipped', recipient: (string) $agent->id, note: 'agent has no phone number on file');

                continue;
            }

            try {
                $messageId = SemaphoreSms::send($agent->phone, $body);

                if ($messageId) {
                    Log::info("Agent SMS order summary sent for {$order->po_number} (Semaphore message id {$messageId}).");
                    self::record($order, 'agent_sms', 'sent', recipient: $agent->phone, externalReference: (string) $messageId);
                } else {
                    self::record($order, 'agent_sms', 'failed', recipient: $agent->phone, note: 'Semaphore did not return a message id');
                }
            } catch (\Throwable $e) {
                Log::warning("Failed to send agent SMS order summary to user {$agent->id} for {$order->po_number}.", ['exception' => $e]);
                self::record($order, 'agent_sms', 'failed', recipient: $agent->phone, note: $e->getMessage());
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

    /**
     * Same content as facebookSummaryBody() -- an agent reading this over
     * SMS needs to be able to act on it exactly as they would from
     * Messenger, so it isn't trimmed down the way the customer-facing
     * smsSummaryBody() is.
     */
    private static function agentSmsSummaryBody(PurchaseOrder $order): string
    {
        return self::facebookSummaryBody($order);
    }
}
