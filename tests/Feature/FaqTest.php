<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_view_the_faq_page(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->get('/faq')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Faq'));
    }

    public function test_guests_are_redirected_from_the_faq_page(): void
    {
        $this->get('/faq')->assertRedirect('/login');
    }
}
