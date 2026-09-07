<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TermsAndPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_terms_and_privacy_page_is_available_to_guests(): void
    {
        $this->get('/terms-and-privacy')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('TermsAndPrivacy'));
    }

    public function test_terms_and_privacy_page_is_available_to_signed_in_users(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->get('/terms-and-privacy')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('TermsAndPrivacy'));
    }
}
