<?php

namespace Tests\Feature;

use App\Models\SearchSelection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_recorded_selection_shows_up_in_recent(): void
    {
        $user = User::factory()->create(['role' => 'office']);

        $this->actingAsUser($user)
            ->postJson(route('search.record'), [
                'context' => 'customer',
                'entity_key' => '42',
                'label' => 'Acme Co',
            ])
            ->assertCreated();

        $response = $this->actingAsUser($user)->getJson(route('search.recent', ['context' => 'customer']));

        $response->assertOk();
        $this->assertSame([['entity_key' => '42', 'label' => 'Acme Co']], $response->json('recent'));
    }

    public function test_recent_is_scoped_to_the_current_user(): void
    {
        $me = User::factory()->create(['role' => 'office']);
        $someoneElse = User::factory()->create(['role' => 'office']);

        $this->actingAsUser($someoneElse)->postJson(route('search.record'), [
            'context' => 'customer',
            'entity_key' => '1',
            'label' => 'Other Person\'s Pick',
        ]);

        $response = $this->actingAsUser($me)->getJson(route('search.recent', ['context' => 'customer']));

        $response->assertOk();
        $this->assertSame([], $response->json('recent'));
    }

    public function test_repicking_the_same_entity_does_not_duplicate_it_in_recent(): void
    {
        $user = User::factory()->create(['role' => 'office']);

        foreach (range(1, 3) as $_) {
            $this->actingAsUser($user)->postJson(route('search.record'), [
                'context' => 'product',
                'entity_key' => '7',
                'label' => 'Widget',
            ]);
        }

        $response = $this->actingAsUser($user)->getJson(route('search.recent', ['context' => 'product']));

        $response->assertOk();
        $this->assertCount(1, $response->json('recent'));
    }

    public function test_popularity_aggregates_across_all_users(): void
    {
        $agentA = User::factory()->create(['role' => 'office']);
        $agentB = User::factory()->create(['role' => 'office']);

        foreach ([$agentA, $agentB] as $agent) {
            $this->actingAsUser($agent)->postJson(route('search.record'), [
                'context' => 'product',
                'entity_key' => '9',
                'label' => 'Popular Widget',
            ]);
        }
        $this->actingAsUser($agentA)->postJson(route('search.record'), [
            'context' => 'product',
            'entity_key' => '10',
            'label' => 'Rare Widget',
        ]);

        $response = $this->actingAsUser($agentA)->getJson(route('search.recent', ['context' => 'product']));

        $response->assertOk();
        $popular = collect($response->json('popular'))->keyBy('entity_key');
        $this->assertSame(2, $popular->get('9')['count']);
        $this->assertSame(1, $popular->get('10')['count']);
    }

    public function test_order_search_context_never_returns_popular(): void
    {
        $user = User::factory()->create(['role' => 'office']);

        $this->actingAsUser($user)->postJson(route('search.record'), [
            'context' => 'order_search',
            'label' => 'PO-12345',
        ]);

        $response = $this->actingAsUser($user)->getJson(route('search.recent', ['context' => 'order_search']));

        $response->assertOk();
        $this->assertSame([], $response->json('popular'));
        $this->assertSame([['entity_key' => null, 'label' => 'PO-12345']], $response->json('recent'));
    }

    public function test_invalid_context_is_rejected(): void
    {
        $user = User::factory()->create(['role' => 'office']);

        $this->actingAsUser($user)
            ->postJson(route('search.record'), ['context' => 'not-a-real-context', 'label' => 'x'])
            ->assertUnprocessable();

        $this->actingAsUser($user)
            ->getJson(route('search.recent', ['context' => 'not-a-real-context']))
            ->assertUnprocessable();
    }

    public function test_label_is_required(): void
    {
        $user = User::factory()->create(['role' => 'office']);

        $this->actingAsUser($user)
            ->postJson(route('search.record'), ['context' => 'customer', 'entity_key' => '1'])
            ->assertUnprocessable();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson(route('search.recent', ['context' => 'customer']))->assertUnauthorized();
        $this->postJson(route('search.record'), ['context' => 'customer', 'label' => 'x'])->assertUnauthorized();
    }

    public function test_recent_only_returns_up_to_five_entries(): void
    {
        $user = User::factory()->create(['role' => 'office']);

        foreach (range(1, 7) as $i) {
            $this->actingAsUser($user)->postJson(route('search.record'), [
                'context' => 'customer',
                'entity_key' => (string) $i,
                'label' => "Customer {$i}",
            ]);
        }

        $response = $this->actingAsUser($user)->getJson(route('search.recent', ['context' => 'customer']));

        $response->assertOk();
        $this->assertCount(5, $response->json('recent'));
        // Most recently picked first.
        $this->assertSame('7', $response->json('recent.0.entity_key'));
    }
}
