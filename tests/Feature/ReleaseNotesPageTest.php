<?php

namespace Tests\Feature;

use App\Models\ReleaseNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseNotesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_authenticated_role_can_view_release_notes_newest_first(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        ReleaseNote::create(['version' => 1, 'title' => 'First release', 'body' => 'Initial notes', 'published_at' => now()->subDay()]);
        ReleaseNote::create(['version' => 2, 'title' => 'Second release', 'body' => 'More notes', 'published_at' => now()]);

        $response = $this->actingAsUser($customer)->get(route('release-notes'));

        $response->assertOk();
        $releases = collect($response->viewData('page')['props']['releases']);
        $this->assertSame(['Second release', 'First release'], $releases->pluck('title')->all());
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('release-notes'))->assertRedirect(route('login'));
    }
}
