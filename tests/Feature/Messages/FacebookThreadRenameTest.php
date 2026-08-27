<?php

namespace Tests\Feature\Messages;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrderFixtures;
use Tests\TestCase;

class FacebookThreadRenameTest extends TestCase
{
    use CreatesOrderFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Match the existing feature-test convention: browser requests carry
        // the CSRF token, while this test class focuses on authorization,
        // validation, and persistence behind that middleware.
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_staff_can_rename_a_facebook_conversation(): void
    {
        $staff = User::factory()->admin()->create();
        $thread = $this->makeThread(null, [
            'channel' => 'facebook_messenger',
            'external_sender_id' => '93901017',
        ]);

        $response = $this->actingAsUser($staff)->patchJson(
            route('messages.widget.facebook.rename', $thread),
            ['name' => 'Facebook Lead'],
        );

        $response
            ->assertOk()
            ->assertJsonPath('thread.name', 'Facebook Lead');
        $this->assertDatabaseHas('customer_messages', [
            'id' => $thread->id,
            'external_sender_name' => 'Facebook Lead',
        ]);
    }

    public function test_customer_cannot_rename_a_facebook_conversation(): void
    {
        $customer = User::factory()->customer()->create();
        $thread = $this->makeThread(null, [
            'channel' => 'facebook_messenger',
            'external_sender_id' => '93901017',
        ]);

        $this->actingAsUser($customer)
            ->patchJson(route('messages.widget.facebook.rename', $thread), ['name' => 'Changed'])
            ->assertForbidden();

        $this->assertNull($thread->fresh()->external_sender_name);
    }

    public function test_rename_requires_a_name_within_the_column_limit(): void
    {
        $staff = User::factory()->create();
        $thread = $this->makeThread(null, [
            'channel' => 'facebook_messenger',
            'external_sender_id' => '93901017',
        ]);

        $this->actingAsUser($staff)
            ->patchJson(route('messages.widget.facebook.rename', $thread), ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->actingAsUser($staff)
            ->patchJson(route('messages.widget.facebook.rename', $thread), ['name' => str_repeat('a', 201)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_portal_conversation_cannot_be_renamed_through_facebook_endpoint(): void
    {
        $staff = User::factory()->create();
        $thread = $this->makeThread();

        $this->actingAsUser($staff)
            ->patchJson(route('messages.widget.facebook.rename', $thread), ['name' => 'Changed'])
            ->assertNotFound();
    }
}
