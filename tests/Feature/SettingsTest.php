<?php

namespace Tests\Feature;

use App\Models\AdminAudit;
use App\Models\User;
use App\Support\OrderNotifications;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_edit_page_loads_for_any_role(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAsUser($user)->get('/settings')->assertOk();
    }

    public function test_update_saves_full_name_and_phone(): void
    {
        $user = User::factory()->create(['full_name' => 'Old Name', 'phone' => '111']);

        $this->actingAsUser($user)->put('/settings', [
            'full_name' => 'New Name',
            'phone' => '222',
        ]);

        $user->refresh();
        $this->assertSame('New Name', $user->full_name);
        $this->assertSame('222', $user->phone);
    }

    public function test_blank_full_name_is_rejected(): void
    {
        $user = User::factory()->create(['full_name' => 'Keep Me']);

        $this->actingAsUser($user)->put('/settings', [
            'full_name' => '',
            'phone' => '',
        ])->assertSessionHasErrors('full_name');

        $this->assertSame('Keep Me', $user->fresh()->full_name);
    }

    public function test_email_and_password_cannot_be_changed_through_settings(): void
    {
        $user = User::factory()->create(['email' => 'original@example.com']);
        $originalHash = $user->password_hash;

        $this->actingAsUser($user)->put('/settings', [
            'full_name' => $user->full_name,
            'phone' => '',
            'email' => 'attacker@example.com',
            'password' => 'newpassword12345',
            'role' => 'admin',
        ]);

        $user->refresh();
        $this->assertSame('original@example.com', $user->email);
        $this->assertSame($originalHash, $user->password_hash);
    }

    public function test_update_records_an_audit_entry(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)->put('/settings', [
            'full_name' => 'Audited Name',
            'phone' => '',
        ]);

        $audit = AdminAudit::where('entity_type', 'user')->where('entity_id', $user->id)->first();
        $this->assertNotNull($audit);
        $this->assertSame($user->id, $audit->actor_user_id);
    }

    public function test_sms_defaults_to_the_env_flag_when_no_override_is_stored(): void
    {
        config(['services.po_notifications.sms_enabled' => true]);
        $this->assertTrue(OrderNotifications::smsEnabled());

        config(['services.po_notifications.sms_enabled' => false]);
        $this->assertFalse(OrderNotifications::smsEnabled());
    }

    public function test_admin_can_turn_sms_off_and_the_override_beats_the_env_flag(): void
    {
        config(['services.po_notifications.sms_enabled' => true]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAsUser($admin)->put('/settings/sms', ['enabled' => false]);

        $this->assertFalse(OrderNotifications::smsEnabled());
        $this->assertDatabaseHas('app_settings', [
            'key' => OrderNotifications::SMS_ENABLED_SETTING,
            'value' => '0',
        ]);
    }

    public function test_admin_can_update_sms_without_an_inertia_page_response(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAsUser($admin)
            ->putJson('/settings/sms', ['enabled' => false])
            ->assertOk()
            ->assertJson(['enabled' => false]);

        $this->assertFalse(OrderNotifications::smsEnabled());
    }

    public function test_admin_can_turn_sms_back_on(): void
    {
        config(['services.po_notifications.sms_enabled' => true]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAsUser($admin)->put('/settings/sms', ['enabled' => false]);
        $this->assertFalse(OrderNotifications::smsEnabled());

        $this->actingAsUser($admin)->put('/settings/sms', ['enabled' => true]);
        $this->assertTrue(OrderNotifications::smsEnabled());
    }

    public function test_turning_sms_off_records_an_audit_entry(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAsUser($admin)->put('/settings/sms', ['enabled' => false]);

        $audit = AdminAudit::where('entity_type', 'app_setting')->first();
        $this->assertNotNull($audit);
        $this->assertSame('sms_disabled', $audit->action);
        $this->assertSame($admin->id, $audit->actor_user_id);
    }

    public function test_non_admins_cannot_change_the_sms_setting(): void
    {
        config(['services.po_notifications.sms_enabled' => true]);

        foreach (['customer', 'employee'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAsUser($user)->put('/settings/sms', ['enabled' => false])->assertForbidden();
        }

        $this->assertTrue(OrderNotifications::smsEnabled());
        $this->assertDatabaseMissing('app_settings', ['key' => OrderNotifications::SMS_ENABLED_SETTING]);
    }

    public function test_only_admins_are_given_the_sms_panel_state(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAsUser($customer)
            ->get('/settings')
            ->assertInertia(fn ($page) => $page->where('sms', null));

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAsUser($admin)
            ->get('/settings')
            ->assertInertia(fn ($page) => $page->has('sms.enabled')->has('sms.configured'));
    }
}
