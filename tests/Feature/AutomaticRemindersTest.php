<?php

namespace Tests\Feature;

use App\Jobs\SendOrderFollowUp;
use App\Jobs\SendOrderFollowUpSms;
use App\Models\AdminAudit;
use App\Models\AppSetting;
use App\Models\OrderFollowUp;
use App\Models\ProductReturn;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderNotification;
use App\Models\User;
use App\Services\OrderFollowUpDispatcher;
use App\Services\OrderFollowUpManager;
use App\Support\ReminderSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class AutomaticRemindersTest extends TestCase
{
    use CreatesOrderFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'reminders.enabled' => true,
            'reminders.customer_sms_enabled' => false,
            'reminders.timezone' => 'Asia/Manila',
        ]);
    }

    public function test_order_lifecycle_opens_refreshes_and_resolves_follow_ups(): void
    {
        $this->freezeTime();
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now(), [
            ['product_id' => $product->id, 'quantity' => 2, 'delivered_quantity' => 0],
        ]);
        $manager = app(OrderFollowUpManager::class);

        $manager->syncOrder($order);
        $this->assertDatabaseHas('order_follow_ups', [
            'purchase_order_id' => $order->id,
            'kind' => OrderFollowUpManager::AWAITING_FULFILLMENT,
            'status' => OrderFollowUp::STATUS_PENDING,
        ]);

        $this->travel(2)->hours();
        $order->status = PurchaseOrder::STATUS_PARTIAL;
        $order->save();
        $manager->syncOrder($order, now());

        $partial = OrderFollowUp::where('kind', OrderFollowUpManager::STALLED_PARTIAL)->firstOrFail();
        $this->assertSame(now()->addHours(48)->format('Y-m-d H:i:s'), $partial->next_due_at->format('Y-m-d H:i:s'));
        $this->assertSame(OrderFollowUp::STATUS_RESOLVED, OrderFollowUp::where('kind', OrderFollowUpManager::AWAITING_FULFILLMENT)->value('status'));

        $order->status = PurchaseOrder::STATUS_PROCESSING;
        $order->save();
        $manager->syncOrder($order, now());
        $this->assertDatabaseHas('order_follow_ups', ['kind' => OrderFollowUpManager::AWAITING_CUSTOMER_CLOSE, 'status' => 'pending']);

        $order->status = PurchaseOrder::STATUS_COMPLETED;
        $order->save();
        $manager->syncOrder($order);
        $this->assertSame(0, OrderFollowUp::where('purchase_order_id', $order->id)->whereNot('status', 'resolved')->count());
    }

    public function test_return_states_use_separate_review_and_receipt_follow_ups(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_PARTIAL, now());
        $return = ProductReturn::create([
            'purchase_order_id' => $order->id,
            'customer_id' => $customer->id,
            'status' => ProductReturn::STATUS_REQUESTED,
            'reason' => 'Damaged package',
            'requested_at' => now(),
        ]);
        $manager = app(OrderFollowUpManager::class);

        $manager->syncReturn($return);
        $this->assertDatabaseHas('order_follow_ups', ['kind' => OrderFollowUpManager::RETURN_REVIEW, 'status' => 'pending']);

        $return->status = ProductReturn::STATUS_APPROVED;
        $return->reviewed_at = now();
        $return->save();
        $manager->syncReturn($return);
        $this->assertDatabaseHas('order_follow_ups', ['kind' => OrderFollowUpManager::RETURN_REVIEW, 'status' => 'resolved']);
        $this->assertDatabaseHas('order_follow_ups', ['kind' => OrderFollowUpManager::RETURN_RECEIPT, 'status' => 'pending']);

        $return->status = ProductReturn::STATUS_RECEIVED;
        $return->received_at = now();
        $return->save();
        $manager->syncReturn($return);
        $this->assertDatabaseHas('order_follow_ups', ['kind' => OrderFollowUpManager::RETURN_RECEIPT, 'status' => 'resolved']);
    }

    public function test_separate_returns_on_one_order_receive_distinct_follow_up_cycles(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_PARTIAL, now());
        $manager = app(OrderFollowUpManager::class);

        foreach (['First damaged package', 'Second damaged package'] as $reason) {
            $return = ProductReturn::create([
                'purchase_order_id' => $order->id,
                'customer_id' => $customer->id,
                'status' => ProductReturn::STATUS_REQUESTED,
                'reason' => $reason,
                'requested_at' => now(),
            ]);
            $manager->syncReturn($return);
            $return->update(['status' => ProductReturn::STATUS_REJECTED]);
            $manager->syncReturn($return->fresh());
        }

        $this->assertSame(
            [1, 2],
            OrderFollowUp::where('purchase_order_id', $order->id)
                ->where('kind', OrderFollowUpManager::RETURN_REVIEW)
                ->orderBy('cycle')->pluck('cycle')->all(),
        );
    }

    public function test_due_command_claims_each_follow_up_once(): void
    {
        Queue::fake();
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now()->subDays(2));
        app(OrderFollowUpManager::class)->syncOrder($order);

        $this->artisan('orders:dispatch-follow-ups')->assertSuccessful();
        $this->artisan('orders:dispatch-follow-ups')->assertSuccessful();

        Queue::assertPushed(SendOrderFollowUp::class, 1);
        $this->assertSame(OrderFollowUp::STATUS_DISPATCHING, OrderFollowUp::first()->status);
    }

    public function test_reconciliation_uses_rollout_grace_and_validates_before_writing(): void
    {
        $this->freezeTime();
        config(['reminders.rollout_grace_hours' => 24]);
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now()->subDays(10));

        $this->artisan('orders:reconcile-follow-ups')->assertSuccessful();
        $this->assertSame(
            now()->addHours(24)->format('Y-m-d H:i:s'),
            OrderFollowUp::where('purchase_order_id', $order->id)->firstOrFail()->next_due_at->format('Y-m-d H:i:s'),
        );

        OrderFollowUp::query()->delete();
        $this->artisan('orders:reconcile-follow-ups --grace-hours=invalid')->assertFailed();
        $this->assertDatabaseCount('order_follow_ups', 0);
    }

    public function test_assigned_agent_gets_initial_reminder_and_only_target_can_read_it(): void
    {
        $agent = User::factory()->create(['role' => User::ROLE_AGENT]);
        $otherAgent = User::factory()->create(['role' => User::ROLE_AGENT]);
        $customer = $this->makeCustomer();
        $customer->update(['assigned_employee_id' => $agent->id]);
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now()->subDays(2));
        app(OrderFollowUpManager::class)->syncOrder($order);
        $followUp = OrderFollowUp::firstOrFail();
        $followUp->update(['status' => OrderFollowUp::STATUS_DISPATCHING]);

        app(OrderFollowUpDispatcher::class)->dispatch($followUp->fresh());

        $this->assertDatabaseHas('purchase_order_notifications', [
            'purchase_order_id' => $order->id,
            'recipient_user_id' => $agent->id,
            'channel' => 'portal',
            'level' => 'reminder',
        ]);
        $this->actingAsUser($agent)->getJson(route('notifications.recent'))->assertJsonPath('count', 1);
        $this->actingAsUser($otherAgent)->getJson(route('notifications.recent'))->assertJsonPath('count', 0);
    }

    public function test_escalation_goes_to_active_office_and_admin_accounts_only(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $office = User::factory()->create(['role' => User::ROLE_OFFICE]);
        $agent = User::factory()->create(['role' => User::ROLE_AGENT]);
        $inactiveAdmin = User::factory()->inactive()->create(['role' => User::ROLE_ADMIN]);
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now()->subDays(4));
        app(OrderFollowUpManager::class)->syncOrder($order);
        $followUp = OrderFollowUp::firstOrFail();
        $followUp->update(['status' => 'dispatching', 'level' => 'escalation', 'next_due_at' => now()->subMinute()]);

        app(OrderFollowUpDispatcher::class)->dispatch($followUp->fresh());

        $recipients = PurchaseOrderNotification::where('channel', 'portal')->pluck('recipient_user_id')->sort()->values()->all();
        $this->assertSame(collect([$admin->id, $office->id])->sort()->values()->all(), $recipients);
        $this->assertNotContains($agent->id, $recipients);
        $this->assertNotContains($inactiveAdmin->id, $recipients);
    }

    public function test_customer_reminder_uses_portal_and_optional_semaphore_sms(): void
    {
        config([
            'reminders.customer_sms_enabled' => true,
            'services.po_notifications.sms_enabled' => true,
            'services.semaphore.api_key' => 'test-key',
        ]);
        Http::fake(['api.semaphore.co/*' => Http::response([['message_id' => 91, 'status' => 'Queued']])]);
        $this->travelTo(now()->setTimezone('Asia/Manila')->setTime(10, 0)->utc());
        $customerUser = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'phone' => '09171234567']);
        $customer = $this->makeCustomer('Hospital', $customerUser);
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_PROCESSING, now()->subDays(2));
        app(OrderFollowUpManager::class)->syncOrder($order);
        $followUp = OrderFollowUp::firstOrFail();
        $followUp->update(['status' => 'dispatching']);

        app(OrderFollowUpDispatcher::class)->dispatch($followUp->fresh());

        Http::assertSent(fn ($request) => $request['number'] === '09171234567'
            && str_contains($request['message'], $order->po_number)
            && ! str_contains($request['message'], 'Hospital'));
        $this->assertDatabaseHas('purchase_order_notifications', ['recipient_user_id' => $customerUser->id, 'channel' => 'portal', 'status' => 'sent']);
        $this->assertDatabaseHas('purchase_order_notifications', ['recipient_user_id' => $customerUser->id, 'channel' => 'sms', 'status' => 'sent']);
    }

    public function test_customer_sms_waits_until_quiet_hours_end(): void
    {
        Queue::fake();
        config([
            'reminders.customer_sms_enabled' => true,
            'services.po_notifications.sms_enabled' => true,
            'services.semaphore.api_key' => 'test-key',
        ]);
        $this->travelTo(now()->setTimezone('Asia/Manila')->setTime(22, 0)->utc());
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'phone' => '09171234567']);
        $customer = $this->makeCustomer('Hospital', $user);
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_PROCESSING, now()->subDays(2));
        app(OrderFollowUpManager::class)->syncOrder($order);
        $followUp = OrderFollowUp::firstOrFail();
        $followUp->update(['status' => 'dispatching']);

        app(OrderFollowUpDispatcher::class)->dispatch($followUp->fresh());

        $this->assertDatabaseHas('purchase_order_notifications', ['channel' => 'portal', 'recipient_user_id' => $user->id]);
        $this->assertDatabaseMissing('purchase_order_notifications', ['channel' => 'sms']);
        Queue::assertPushed(SendOrderFollowUpSms::class, fn ($job) => $job->followUpId === $followUp->id && $job->recipientUserId === $user->id);
    }

    public function test_stale_queued_follow_up_resolves_without_notifying(): void
    {
        $office = User::factory()->create(['role' => User::ROLE_OFFICE]);
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_SUBMITTED, now()->subDays(2));
        app(OrderFollowUpManager::class)->syncOrder($order);
        $followUp = OrderFollowUp::firstOrFail();
        $followUp->update(['status' => 'dispatching']);
        $order->update(['status' => PurchaseOrder::STATUS_CANCELLED]);

        app(OrderFollowUpDispatcher::class)->dispatch($followUp->fresh());

        $this->assertSame(OrderFollowUp::STATUS_RESOLVED, $followUp->fresh()->status);
        $this->assertSame(0, PurchaseOrderNotification::where('recipient_user_id', $office->id)->count());
    }

    public function test_only_admin_can_update_validated_reminder_settings(): void
    {
        $payload = ReminderSettings::settingsPayload();
        unset($payload['timezone'], $payload['last_scheduler_run'], $payload['last_failure']);

        foreach ([User::ROLE_OFFICE, User::ROLE_AGENT, User::ROLE_CUSTOMER] as $role) {
            $this->actingAsUser(User::factory()->create(['role' => $role]))
                ->putJson(route('settings.reminders.update'), $payload)
                ->assertForbidden();
        }

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $payload['enabled'] = false;
        $this->actingAsUser($admin)->putJson(route('settings.reminders.update'), $payload)
            ->assertOk()->assertJsonPath('enabled', false);

        $this->assertFalse(AppSetting::boolean(ReminderSettings::ENABLED_KEY, true));
        $this->assertDatabaseHas('admin_audits', ['action' => 'order_reminders_updated', 'actor_user_id' => $admin->id]);
    }

    public function test_invalid_threshold_order_is_explained_and_not_saved(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $payload = ReminderSettings::settingsPayload();
        unset($payload['timezone'], $payload['last_scheduler_run'], $payload['last_failure']);
        $payload['thresholds']['return_review']['reminder'] = 50;
        $payload['thresholds']['return_review']['escalation'] = 25;

        $this->actingAsUser($admin)->putJson(route('settings.reminders.update'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('thresholds.return_review.escalation');
        $this->assertSame(0, AdminAudit::where('action', 'order_reminders_updated')->count());
    }
}
