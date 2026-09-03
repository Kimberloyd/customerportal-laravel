<?php

namespace Tests\Feature\Admin\Users;

use App\Models\AdminAudit;
use App\Models\DataSubjectRequest;
use App\Models\LoginAttempt;
use App\Models\ProductReturn;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class DeleteTest extends TestCase
{
    use CreatesOrderFixtures;
    use RefreshDatabase;

    public function test_self_delete_is_blocked(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAsUser($admin)
            ->delete("/admin/users/{$admin->id}");

        $response->assertRedirect();
        $response->assertSessionHas('error', 'You cannot delete your current account.');

        $this->assertNotNull(User::find($admin->id));
    }

    public function test_delete_immediately_blocks_access_and_schedules_purge(): void
    {
        config(['account-deletion.retention_days' => 30]);
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['session_version' => 4]);

        DB::table('sessions')->insert([
            'id' => 'target-session', 'user_id' => $target->id,
            'ip_address' => '127.0.0.1', 'user_agent' => 'test',
            'payload' => 'payload', 'last_activity' => now()->timestamp,
        ]);
        DB::table('password_reset_tokens')->insert([
            'email' => $target->email, 'token' => 'secret-token', 'created_at' => now(),
        ]);

        $response = $this->actingAsUser($admin)->delete("/admin/users/{$target->id}");

        $response->assertRedirect(route('admin.dashboard', ['tab' => 'accounts']));
        $this->assertNull(User::find($target->id));
        $scheduled = User::withTrashed()->findOrFail($target->id);
        $this->assertTrue($scheduled->trashed());
        $this->assertFalse($scheduled->is_active);
        $this->assertSame(5, $scheduled->session_version);
        $this->assertTrue($scheduled->purge_after->between(now()->addDays(29), now()->addDays(31)));
        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $target->email]);
        $this->assertDatabaseHas('data_subject_requests', [
            'subject_user_id' => $target->id, 'request_type' => 'routine_deletion', 'status' => 'scheduled',
        ]);
    }

    public function test_linked_customer_is_retained_during_recovery_window_and_unlinked_at_purge(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->customer()->create();
        $customer = $this->makeCustomer('Own Co', $target);

        $this->actingAsUser($admin)->delete("/admin/users/{$target->id}");
        $this->assertSame($target->id, $customer->fresh()->user_id);

        User::withTrashed()->findOrFail($target->id)->forceFill(['purge_after' => now()->subMinute()])->save();
        $this->artisan('accounts:purge-deleted')->assertSuccessful();

        $this->assertNull(User::withTrashed()->find($target->id));
        $this->assertNotNull($customer->fresh());
        $this->assertNull($customer->fresh()->user_id);
    }

    public function test_admin_can_restore_account_during_retention_window(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAsUser($admin)->delete("/admin/users/{$target->id}");

        $this->actingAsUser($admin)
            ->post("/admin/users/{$target->id}/restore")
            ->assertSessionHas('success', "{$target->full_name}'s account was restored.");

        $restored = User::findOrFail($target->id);
        $this->assertTrue($restored->is_active);
        $this->assertNull($restored->purge_after);
        $this->assertDatabaseHas('data_subject_requests', [
            'subject_user_id' => $target->id, 'request_type' => 'routine_deletion', 'status' => 'cancelled',
        ]);
    }

    public function test_purge_removes_personal_authentication_history_and_anonymizes_audit(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['email' => 'doomed@example.com']);
        LoginAttempt::create([
            'email' => $target->email, 'ip_address' => '192.0.2.10',
            'successful' => false, 'created_at' => now(),
        ]);

        $this->actingAsUser($admin)->delete("/admin/users/{$target->id}");
        User::withTrashed()->findOrFail($target->id)->forceFill(['purge_after' => now()->subMinute()])->save();
        $this->artisan('accounts:purge-deleted')->assertSuccessful();

        $this->assertDatabaseMissing('login_attempts', ['email' => 'doomed@example.com']);
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseHas('admin_audits', [
            'entity_type' => 'user', 'entity_id' => null,
            'details' => 'Personal data removed after account erasure.',
        ]);
        $this->assertDatabaseHas('data_subject_requests', [
            'subject_user_id' => null, 'request_type' => 'routine_deletion', 'status' => 'completed',
        ]);
        $this->assertNotNull(AdminAudit::where('action', 'account erasure completed')->first());
    }

    public function test_purge_retains_return_history_but_anonymizes_the_requester(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->customer()->create();
        $customer = $this->makeCustomer('Return Customer', $target);
        $order = $this->makeOrder($customer, PurchaseOrder::STATUS_COMPLETED, now(), [
            ['product_id' => $this->makeProduct()->id, 'quantity' => 1, 'delivered_quantity' => 1],
        ]);
        $return = ProductReturn::create([
            'purchase_order_id' => $order->id,
            'customer_id' => $customer->id,
            'requested_by_user_id' => $target->id,
            'status' => ProductReturn::STATUS_REQUESTED,
            'reason' => 'Contains a customer-provided return explanation.',
            'requested_at' => now(),
        ]);

        $this->actingAsUser($admin)->delete("/admin/users/{$target->id}");
        User::withTrashed()->findOrFail($target->id)->forceFill(['purge_after' => now()->subMinute()])->save();
        $this->artisan('accounts:purge-deleted')->assertSuccessful();

        $this->assertDatabaseHas('product_returns', [
            'id' => $return->id,
            'requested_by_user_id' => null,
            'reason' => 'Personal data removed after account erasure.',
        ]);
    }

    public function test_data_export_excludes_credentials_and_records_request(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->customer()->create(['email' => 'report@example.com']);
        $customer = $this->makeCustomer('Report Customer', $target);
        $conversation = $this->makeThread($customer, ['body' => 'Include this message.']);

        $response = $this->actingAsUser($admin)->get("/admin/users/{$target->id}/data-export");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');
        $payload = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('report@example.com', $payload['account']['email']);
        $this->assertArrayNotHasKey('password_hash', $payload['account']);
        $this->assertSame($conversation->id, $payload['conversations'][0]['id']);
        $this->assertSame('Include this message.', $payload['conversations'][0]['body']);
        $this->assertDatabaseHas('data_subject_requests', [
            'subject_user_id' => $target->id, 'request_type' => 'data_export', 'status' => 'completed',
        ]);
    }

    public function test_formal_erasure_bypasses_retention_and_records_completion(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->customer()->create();
        $customer = $this->makeCustomer('Retained Customer', $target);

        $this->actingAsUser($admin)
            ->delete("/admin/users/{$target->id}/erase-now", [
                'confirmation' => $target->full_name,
            ])
            ->assertSessionHas('success');

        $this->assertNull(User::withTrashed()->find($target->id));
        $this->assertNull($customer->fresh()->user_id);
        $this->assertDatabaseHas('data_subject_requests', [
            'subject_user_id' => null,
            'request_type' => 'erasure',
            'status' => 'completed',
        ]);
    }

    public function test_formal_erasure_succeeds_when_optional_auth_tables_are_absent(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->customer()->create();
        $customer = $this->makeCustomer('Customer Without Auth Tables', $target);

        Schema::drop('sessions');
        Schema::drop('password_reset_tokens');

        $this->actingAsUser($admin)
            ->delete("/admin/users/{$target->id}/erase-now", [
                'confirmation' => $target->full_name,
            ])
            ->assertSessionHas('success');

        $this->assertNull(User::withTrashed()->find($target->id));
        $this->assertNull($customer->fresh()->user_id);
        $this->assertDatabaseHas('data_subject_requests', [
            'subject_user_id' => null,
            'request_type' => 'erasure',
            'status' => 'completed',
        ]);
    }

    public function test_a_non_admin_cannot_schedule_or_export_account_data(): void
    {
        $employee = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAsUser($employee)->delete("/admin/users/{$target->id}")->assertForbidden();
        $this->actingAsUser($employee)->get("/admin/users/{$target->id}/data-export")->assertForbidden();
        $this->assertSame(0, DataSubjectRequest::count());
    }
}
