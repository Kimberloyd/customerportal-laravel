<?php

namespace Tests\Unit;

use App\Support\InventoryApiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InventoryApiClientTest extends TestCase
{
    public function test_all_products_fetches_every_page_and_preserves_page_order(): void
    {
        $this->fakeInventoryApi(products: array_map(
            fn (int $id) => ['id' => $id],
            range(1, 205)
        ));

        $products = app(InventoryApiClient::class)->allProducts();

        $this->assertSame(range(1, 205), array_column($products, 'id'));
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'page=3'));
    }

    public function test_all_customers_fetches_every_page_and_preserves_page_order(): void
    {
        $this->fakeInventoryApi(customers: array_map(
            fn (int $id) => ['id' => $id],
            range(1, 101)
        ));

        $customers = app(InventoryApiClient::class)->allCustomers();

        $this->assertSame(range(1, 101), array_column($customers, 'id'));
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/customers'));
    }

    public function test_pagination_falls_back_to_sequential_requests_when_total_is_missing(): void
    {
        $this->fakeInventoryApi(
            products: array_map(fn (int $id) => ['id' => $id], range(1, 101)),
            includeTotal: false,
        );

        $products = app(InventoryApiClient::class)->allProducts();

        $this->assertSame(range(1, 101), array_column($products, 'id'));
        Http::assertSentCount(2);
    }
}
