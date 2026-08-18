<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Thin client for the inventoryapp product catalog
 * (GET {base_url}/products). Products no longer live in this app's own
 * database -- see the 2026_08_18 migration that dropped the local
 * `products` table.
 */
class InventoryApiClient
{
    private const MAX_PAGE_SIZE = 100;

    /**
     * Fetch every product matching the given query params, paging through
     * the API's max page size (100) until `has_next` is false.
     *
     * @param  array<string, mixed>  $params  Extra query params (e.g. q, status).
     * @return array<int, array<string, mixed>>
     */
    public function allProducts(array $params = []): array
    {
        $products = [];
        $page = 1;

        do {
            $response = $this->request($params + [
                'page' => $page,
                'limit' => self::MAX_PAGE_SIZE,
            ]);

            $products = array_merge($products, $response['data'] ?? []);
            $hasNext = (bool) ($response['meta']['has_next'] ?? false);
            $page++;
        } while ($hasNext);

        return $products;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>, ok: bool}
     */
    public function request(array $params = []): array
    {
        $response = Http::baseUrl(config('services.inventory_api.base_url'))
            ->withToken(config('services.inventory_api.token'))
            ->timeout(15)
            ->get('/products', $params)
            ->throw();

        return $response->json();
    }

    /**
     * Maps one raw API product row onto the field names the rest of this
     * app expects (matching the old local `products` table's columns).
     *
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    public static function mapProduct(array $product): array
    {
        return [
            'id' => $product['id'],
            'sku' => $product['sku'],
            'product_name' => $product['product_name'] ?? $product['name'],
            'category' => $product['category'],
            'generic_name' => $product['generic'],
            'description' => $product['description'],
            'dosage' => $product['dosage'],
            'unit' => $product['unit_type'],
            'unit_price' => $product['current_price'],
            'is_active' => (bool) $product['is_active'],
        ];
    }
}
