<?php

namespace Tests\Feature\Customers;

use App\Models\AdminAudit;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class CreateTest extends TestCase
{
    use RefreshDatabase;
    use CreatesOrderFixtures;

    public function test_customer_role_gets_403(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAsUser($user)->get('/customers/create')->assertStatus(403);
        $this->actingAsUser($user)->post('/customers', [
            'company_name' => 'Acme',
            'channel' => 'HOSPITAL',
        ])->assertStatus(403);
    }

    public function test_company_name_required(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);

        $response = $this->actingAsUser($staff)->post('/customers', [
            'company_name' => '',
            'channel' => 'HOSPITAL',
        ]);

        $response->assertSessionHasErrors('company_name');
        $this->assertSame(0, Customer::count());
    }

    public function test_invalid_channel_rejected_not_coerced(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);

        $response = $this->actingAsUser($staff)->post('/customers', [
            'company_name' => 'Acme',
            'channel' => 'NOT-A-REAL-CHANNEL',
        ]);

        $response->assertSessionHasErrors('channel');
        $this->assertSame(0, Customer::count());
    }

    public function test_blank_channel_defaults_to_others(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);

        $this->actingAsUser($staff)->post('/customers', [
            'company_name' => 'Acme',
            'channel' => '',
        ]);

        $this->assertSame('OTHERS', Customer::first()->channel);
    }

    public function test_customer_code_generated_from_multi_word_name(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);

        $this->actingAsUser($staff)->post('/customers', [
            'company_name' => 'Acme Medical Supplies',
            'channel' => 'HOSPITAL',
        ]);

        $customer = Customer::first();
        $this->assertSame("AMS-{$customer->id}", $customer->customer_code);
    }

    public function test_customer_code_truncates_to_eight_characters(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);

        $this->actingAsUser($staff)->post('/customers', [
            'company_name' => 'One Two Three Four Five Six Seven Eight Nine Ten',
            'channel' => 'HOSPITAL',
        ]);

        $customer = Customer::first();
        $this->assertSame("OTTFFSSE-{$customer->id}", $customer->customer_code);
    }

    public function test_customer_code_falls_back_to_cust_for_all_punctuation_name(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);

        $this->actingAsUser($staff)->post('/customers', [
            'company_name' => '---',
            'channel' => 'HOSPITAL',
        ]);

        $customer = Customer::first();
        $this->assertSame("CUST-{$customer->id}", $customer->customer_code);
    }

    public function test_writes_audit_row_with_exact_details_format(): void
    {
        $staff = User::factory()->create(['role' => 'employee']);

        $this->actingAsUser($staff)->post('/customers', [
            'company_name' => 'Acme Co',
            'channel' => 'HOSPITAL',
        ]);

        $audit = AdminAudit::first();
        $this->assertSame('customer', $audit->entity_type);
        $this->assertSame('created', $audit->action);
        $this->assertSame('company_name=Acme Co', $audit->details);
        $this->assertSame($staff->id, $audit->actor_user_id);
    }
}
