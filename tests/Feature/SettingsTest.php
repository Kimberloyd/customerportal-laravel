<?php

namespace Tests\Feature;

use App\Models\AdminAudit;
use App\Models\User;
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
}
