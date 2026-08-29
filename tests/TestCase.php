<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Laravel 13's default CSRF middleware is PreventRequestForgery.
        // Feature tests exercise application actions directly rather than a
        // browser session, so keep CSRF enabled in production and disable only
        // this middleware in the test harness.
        $this->withoutMiddleware(PreventRequestForgery::class);

        // Products (and the customers:sync source) come from the live
        // inventoryapp API, which three controllers call while handling a
        // request. Without this the suite makes real authenticated HTTPS
        // calls to production on nearly every test -- slow, dependent on
        // that service being up, and issuing real traffic from CI.
        //
        // Stray-request prevention makes any unfaked host fail loudly
        // rather than silently escaping to the network.
        Http::preventStrayRequests();

        // Resolved lazily: Http::fake() *merges* stubs and the first match
        // wins, so a stub registered here with a fixed payload would shadow
        // any later one. Reading the properties at request time instead lets
        // fixtures keep seeding the catalogue after setUp() has run.
        Http::fake([
            '*/products*' => fn (ClientRequest $request) => Http::response(
                $this->inventoryPage($this->inventoryProducts, $request)
            ),
            '*/customers*' => fn (ClientRequest $request) => Http::response(
                $this->inventoryPage($this->inventoryCustomers, $request)
            ),
        ]);
    }

    /**
     * Rows the faked catalogue endpoints will return, in the upstream API's
     * own field vocabulary.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $inventoryProducts = [];

    /** @var array<int, array<string, mixed>> */
    protected array $inventoryCustomers = [];

    protected bool $inventoryIncludeTotal = true;

    /**
     * Seed what the faked inventoryapp endpoints return.
     *
     * @param  array<int, array<string, mixed>>  $products
     * @param  array<int, array<string, mixed>>  $customers
     */
    protected function fakeInventoryApi(
        array $products = [],
        array $customers = [],
        bool $includeTotal = true,
    ): void {
        $this->inventoryProducts = $products;
        $this->inventoryCustomers = $customers;
        $this->inventoryIncludeTotal = $includeTotal;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function inventoryPage(array $rows, ClientRequest $request): array
    {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $page = max((int) ($query['page'] ?? 1), 1);
        $limit = min(max((int) ($query['limit'] ?? 100), 1), 100);
        $offset = ($page - 1) * $limit;

        $meta = [
            'has_next' => $offset + $limit < count($rows),
            'page' => $page,
            'limit' => $limit,
        ];
        if ($this->inventoryIncludeTotal) {
            $meta['total'] = count($rows);
        }

        return [
            'data' => array_slice($rows, $offset, $limit),
            'meta' => $meta,
            'ok' => true,
        ];
    }

    /**
     * Plain actingAs() doesn't know about this app's session_version
     * convention (stamped at real login, see
     * AuthenticatedSessionController::store()) -- EnsureSessionVersionMatches
     * would otherwise see a session with no session_version at all,
     * treat that as a mismatch against the user's real value, and force
     * a logout on the very next request. Use this in place of bare
     * actingAs() for any test that needs to get past that middleware.
     */
    protected function actingAsUser(User $user, ?string $guard = null): static
    {
        $this->withSession(['session_version' => $user->session_version]);

        return $this->actingAs($user, $guard);
    }
}
