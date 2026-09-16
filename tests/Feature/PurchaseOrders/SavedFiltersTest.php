<?php

namespace Tests\Feature\PurchaseOrders;

use App\Models\SavedOrderFilter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_saved_filter_shows_up_in_the_list(): void
    {
        $user = User::factory()->create(['role' => 'office']);

        $this->actingAsUser($user)
            ->postJson(route('purchase-orders.saved-filters.store'), [
                'name' => 'My pending orders',
                'filters' => ['status' => 'pending', 'customer_id' => null, 'date_filter' => 'all', 'start_date' => null, 'end_date' => null],
            ])
            ->assertCreated();

        $response = $this->actingAsUser($user)->getJson(route('purchase-orders.saved-filters.index'));

        $response->assertOk();
        $this->assertSame('My pending orders', $response->json('0.name'));
        $this->assertSame('pending', $response->json('0.filters.status'));
    }

    public function test_saved_filters_are_scoped_to_the_current_user(): void
    {
        $me = User::factory()->create(['role' => 'office']);
        $someoneElse = User::factory()->create(['role' => 'office']);

        $this->actingAsUser($someoneElse)->postJson(route('purchase-orders.saved-filters.store'), [
            'name' => 'Not mine',
            'filters' => ['status' => 'all'],
        ]);

        $response = $this->actingAsUser($me)->getJson(route('purchase-orders.saved-filters.index'));

        $response->assertOk();
        $this->assertSame([], $response->json());
    }

    public function test_a_user_cannot_delete_someone_elses_saved_filter(): void
    {
        $owner = User::factory()->create(['role' => 'office']);
        $other = User::factory()->create(['role' => 'office']);
        $filter = SavedOrderFilter::create(['user_id' => $owner->id, 'name' => 'Owner\'s filter', 'filters' => ['status' => 'all']]);

        $this->actingAsUser($other)
            ->deleteJson(route('purchase-orders.saved-filters.destroy', $filter))
            ->assertForbidden();

        $this->assertDatabaseHas('saved_order_filters', ['id' => $filter->id]);
    }

    public function test_a_user_can_delete_their_own_saved_filter(): void
    {
        $user = User::factory()->create(['role' => 'office']);
        $filter = SavedOrderFilter::create(['user_id' => $user->id, 'name' => 'Mine', 'filters' => ['status' => 'all']]);

        $this->actingAsUser($user)
            ->deleteJson(route('purchase-orders.saved-filters.destroy', $filter))
            ->assertNoContent();

        $this->assertDatabaseMissing('saved_order_filters', ['id' => $filter->id]);
    }

    public function test_name_is_required(): void
    {
        $user = User::factory()->create(['role' => 'office']);

        $this->actingAsUser($user)
            ->postJson(route('purchase-orders.saved-filters.store'), ['filters' => ['status' => 'all']])
            ->assertJsonValidationErrors('name');
    }

    public function test_the_per_user_cap_is_enforced(): void
    {
        $user = User::factory()->create(['role' => 'office']);
        for ($i = 0; $i < SavedOrderFilter::MAX_PER_USER; $i++) {
            SavedOrderFilter::create(['user_id' => $user->id, 'name' => "Filter {$i}", 'filters' => ['status' => 'all']]);
        }

        $this->actingAsUser($user)
            ->postJson(route('purchase-orders.saved-filters.store'), [
                'name' => 'One too many',
                'filters' => ['status' => 'all'],
            ])
            ->assertStatus(422);

        $this->assertSame(SavedOrderFilter::MAX_PER_USER, SavedOrderFilter::where('user_id', $user->id)->count());
    }
}
