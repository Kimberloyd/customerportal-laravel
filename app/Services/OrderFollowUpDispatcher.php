<?php

namespace App\Services;

use App\Events\PurchaseOrderChanged;
use App\Jobs\SendOrderFollowUpSms;
use App\Models\AppSetting;
use App\Models\OrderFollowUp;
use App\Models\PurchaseOrderNotification;
use App\Models\User;
use App\Support\OrderNotifications;
use App\Support\ReminderSettings;
use App\Support\SemaphoreSms;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class OrderFollowUpDispatcher
{
    public function __construct(private readonly OrderFollowUpManager $manager) {}

    public function dispatch(OrderFollowUp $followUp): void
    {
        $followUp->loadMissing(['purchaseOrder.customer.user', 'purchaseOrder.customer.assignedAgent', 'productReturn']);

        $applicable = $this->manager->isApplicable($followUp);
        if (! ReminderSettings::enabled() || ! $applicable) {
            if (! $applicable) {
                $followUp->update(['status' => OrderFollowUp::STATUS_RESOLVED, 'resolved_at' => now(), 'next_due_at' => null]);
            } elseif ($followUp->status === OrderFollowUp::STATUS_DISPATCHING) {
                $followUp->update(['status' => OrderFollowUp::STATUS_PENDING]);
            }

            return;
        }

        $recipients = $this->recipients($followUp);
        $message = $this->message($followUp);
        $successful = 0;

        foreach ($recipients as $recipient) {
            $successful += $this->recordPortal($followUp, $recipient, $message) ? 1 : 0;

            if ($recipient->role === User::ROLE_CUSTOMER && $this->customerSmsAllowed()) {
                if ($this->isQuietTime()) {
                    SendOrderFollowUpSms::dispatch($followUp->id, $recipient->id, $followUp->level)
                        ->delay($this->nextSmsWindow());
                } else {
                    $this->sendSms($followUp, $recipient, $this->smsMessage($followUp), $followUp->level);
                }
            }
        }

        $followUp->attempt_count++;
        $followUp->last_dispatched_at = now();

        if ($successful === 0) {
            $followUp->status = OrderFollowUp::STATUS_PENDING;
            $followUp->last_error_at = now();
            $followUp->next_due_at = now()->addHour();
            AppSetting::putString('order_reminders.last_failure', now()->toIso8601String().' No active recipient for follow-up '.$followUp->id.'.');
        } else {
            $this->advance($followUp);
            PurchaseOrderChanged::dispatch($followUp->purchase_order_id, 'follow-up');
        }

        $followUp->save();
    }

    /** @return Collection<int, User> */
    private function recipients(OrderFollowUp $followUp): Collection
    {
        if ($followUp->level === 'escalation') {
            return User::query()->where('is_active', true)
                ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_OFFICE])->get()->unique('id')->values();
        }

        $customer = $followUp->purchaseOrder->customer;
        $users = collect();

        if (in_array($followUp->kind, [OrderFollowUpManager::AWAITING_CUSTOMER_CLOSE, OrderFollowUpManager::RETURN_RECEIPT], true)) {
            if ($customer?->is_active && $customer->user?->is_active && $customer->user->role === User::ROLE_CUSTOMER) {
                $users->push($customer->user);
            }
        }

        if ($followUp->kind !== OrderFollowUpManager::AWAITING_CUSTOMER_CLOSE) {
            $agent = $customer?->assignedAgent;
            if ($agent?->is_active && $agent->role === User::ROLE_AGENT) {
                $users->push($agent);
            } else {
                $users = $users->merge(User::query()->where('is_active', true)->where('role', User::ROLE_OFFICE)->get());
            }
        }

        return $users->unique('id')->values();
    }

    private function recordPortal(OrderFollowUp $followUp, User $recipient, string $message): bool
    {
        $key = $this->dedupeKey($followUp, $recipient, 'portal');
        $record = PurchaseOrderNotification::firstOrCreate(
            ['dedupe_key' => $key],
            [
                'purchase_order_id' => $followUp->purchase_order_id,
                'channel' => 'portal',
                'status' => 'sent',
                'event_key' => 'reminder.'.$followUp->kind,
                'recipient_user_id' => $recipient->id,
                'follow_up_id' => $followUp->id,
                'level' => $followUp->level,
                'recipient' => (string) $recipient->id,
                'note' => $message,
                'created_at' => now(),
            ],
        );

        return $record->wasRecentlyCreated || $record->status === 'sent';
    }

    public function sendDeferredSms(OrderFollowUp $followUp, User $recipient, string $level): void
    {
        if (! ReminderSettings::enabled() || ! $this->manager->isApplicable($followUp) || ! $this->customerSmsAllowed()) {
            return;
        }

        if ($this->isQuietTime()) {
            SendOrderFollowUpSms::dispatch($followUp->id, $recipient->id, $level)->delay($this->nextSmsWindow());

            return;
        }

        $this->sendSms($followUp, $recipient, $this->smsMessage($followUp), $level);
    }

    private function sendSms(OrderFollowUp $followUp, User $recipient, string $message, string $level): void
    {
        if (! $recipient->phone) {
            return;
        }

        $key = $this->dedupeKey($followUp, $recipient, 'sms', $level);
        $record = PurchaseOrderNotification::firstOrCreate(
            ['dedupe_key' => $key],
            [
                'purchase_order_id' => $followUp->purchase_order_id,
                'channel' => 'sms',
                'status' => 'sending',
                'event_key' => 'reminder.'.$followUp->kind,
                'recipient_user_id' => $recipient->id,
                'follow_up_id' => $followUp->id,
                'level' => $level,
                'recipient' => $recipient->phone,
                'note' => 'Customer reminder',
                'created_at' => now(),
            ],
        );

        if (! $record->wasRecentlyCreated) {
            return;
        }

        try {
            $reference = SemaphoreSms::send($recipient->phone, $message);
            $record->update([
                'status' => $reference ? 'sent' : 'failed',
                'external_reference' => $reference,
                'note' => $reference ? 'Customer reminder accepted by SMS provider' : 'SMS provider returned no message reference',
            ]);
        } catch (\Throwable $exception) {
            $record->update(['status' => 'failed', 'note' => 'SMS provider could not accept the reminder']);
            AppSetting::putString('order_reminders.last_failure', now()->toIso8601String().' SMS reminder could not be sent.');
            Log::warning('Order reminder SMS could not be sent.', [
                'follow_up_id' => $followUp->id,
                'purchase_order_id' => $followUp->purchase_order_id,
                'exception' => $exception::class,
            ]);
        }
    }

    private function advance(OrderFollowUp $followUp): void
    {
        $levels = ReminderSettings::levels($followUp->kind);
        $position = array_search($followUp->level, $levels, true);
        $next = $position === false ? null : ($levels[$position + 1] ?? null);

        if ($next === null) {
            $followUp->status = OrderFollowUp::STATUS_ESCALATED;
            $followUp->next_due_at = null;

            return;
        }

        $followUp->level = $next;
        $followUp->status = OrderFollowUp::STATUS_PENDING;
        $followUp->next_due_at = $followUp->triggered_at->copy()->addHours(ReminderSettings::threshold($followUp->kind, $next));
    }

    private function message(OrderFollowUp $followUp): string
    {
        $po = $followUp->purchaseOrder->po_number;
        $hours = ReminderSettings::threshold($followUp->kind, $followUp->level);

        if ($followUp->level === 'escalation') {
            return match ($followUp->kind) {
                OrderFollowUpManager::AWAITING_FULFILLMENT => "Order {$po} is still waiting for fulfillment after {$hours} hours. Assign or follow up with the responsible staff member.",
                OrderFollowUpManager::STALLED_PARTIAL => "Order {$po} has had no delivery update for {$hours} hours. Assign or follow up with the responsible staff member.",
                OrderFollowUpManager::AWAITING_CUSTOMER_CLOSE => "Order {$po} has been fully delivered for 7 days and is still open. Follow up with the customer.",
                OrderFollowUpManager::RETURN_REVIEW => "Return request for order {$po} has been waiting {$hours} hours for a decision. Assign a reviewer.",
                default => "The approved return for order {$po} has been open for 7 days. Follow up and record it when received.",
            };
        }

        return match ($followUp->kind) {
            OrderFollowUpManager::AWAITING_FULFILLMENT => "Order {$po} has been waiting {$hours} hours for its first delivery. Open the order and record an update.",
            OrderFollowUpManager::STALLED_PARTIAL => "Order {$po} has had no delivery update for {$hours} hours. Open the order and record an update.",
            OrderFollowUpManager::AWAITING_CUSTOMER_CLOSE => "Order {$po} has been fully delivered. Review it and close the order when everything is correct.",
            OrderFollowUpManager::RETURN_REVIEW => "Return request for order {$po} has been waiting {$hours} hours for review. Open the request and record a decision.",
            default => "The approved return for order {$po} is still open. Coordinate the return and record it when received.",
        };
    }

    private function smsMessage(OrderFollowUp $followUp): string
    {
        $po = $followUp->purchaseOrder->po_number;

        return $followUp->kind === OrderFollowUpManager::AWAITING_CUSTOMER_CLOSE
            ? "Order {$po} is fully delivered. Please review and close it in the Theomeds customer portal."
            : "Your approved return for order {$po} is still open. Please check the Theomeds customer portal for the next step.";
    }

    private function dedupeKey(OrderFollowUp $followUp, User $recipient, string $channel, ?string $level = null): string
    {
        return implode(':', ['follow-up', $followUp->id, $followUp->cycle, $level ?? $followUp->level, $recipient->id, $channel]);
    }

    private function customerSmsAllowed(): bool
    {
        return ReminderSettings::customerSmsEnabled() && OrderNotifications::smsEnabled() && SemaphoreSms::isConfigured();
    }

    private function isQuietTime(): bool
    {
        $hour = CarbonImmutable::now(ReminderSettings::timezone())->hour;
        $start = ReminderSettings::quietStart();
        $end = ReminderSettings::quietEnd();

        return $start < $end ? $hour >= $start && $hour < $end : $hour >= $start || $hour < $end;
    }

    private function nextSmsWindow(): CarbonImmutable
    {
        $local = CarbonImmutable::now(ReminderSettings::timezone());
        $end = ReminderSettings::quietEnd();
        $next = $local->setTime($end, 0);
        if (! $next->isFuture()) {
            $next = $next->addDay();
        }

        return $next->utc();
    }
}
