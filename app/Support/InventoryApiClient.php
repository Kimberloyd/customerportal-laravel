<?php

namespace App\Support;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Thin client for the inventoryapp catalog (GET {base_url}/products,
 * GET {base_url}/customers). Products no longer live in this app's own
 * database -- see the 2026_08_18 migration that dropped the local
 * `products` table. Customers still live locally (see the customers
 * table + customers:sync command) since purchase_orders.customer_id
 * is a NOT NULL FK into production's shared database, which this app
 * doesn't own or migrate -- see 2026_08_20_...update_customers_for_
 * inventory_sync.php for the full reasoning.
 */
class InventoryApiClient
{
    private const MAX_PAGE_SIZE = 100;

    private const MAX_CONCURRENT_REQUESTS = 4;

    private const PRODUCT_CACHE_TTL_SECONDS = 60;

    /**
     * Fetch every product matching the given query params, using the API's
     * filtered total to request its 100-row pages concurrently.
     *
     * @param  array<string, mixed>  $params  Extra query params (e.g. q, status).
     * @return array<int, array<string, mixed>>
     */
    public function allProducts(array $params = []): array
    {
        return $this->allResources($params, '/products');
    }

    /**
     * Reuse a short-lived catalog snapshot for read-only product pages.
     * Order submission continues to use allProducts() for fresh validation.
     *
     * @param  array<string, mixed>  $params
     * @return array<int, array<string, mixed>>
     */
    public function cachedProducts(array $params = []): array
    {
        ksort($params);
        $cacheKey = 'inventory-api.products.'.hash('sha256', json_encode($params, JSON_THROW_ON_ERROR));

        return Cache::remember(
            $cacheKey,
            self::PRODUCT_CACHE_TTL_SECONDS,
            fn () => $this->allResources($params, '/products'),
        );
    }

    /**
     * Fetch every customer, using the API's total to request its 100-row
     * pages concurrently.
     *
     * @param  array<string, mixed>  $params  Extra query params.
     * @return array<int, array<string, mixed>>
     */
    public function allCustomers(array $params = []): array
    {
        return $this->allResources($params, '/customers');
    }

    /**
     * Fetches, filters, sorts, and maps the product catalog for the
     * Products index page (and anywhere else that embeds the same
     * listing, e.g. the Admin dashboard's Products panel).
     *
     * @param  array<string, mixed>  $query  Raw request query params (search, status, sort_by, sort_dir).
     * @return array{products: array<int, array<string, mixed>>, filters: array<string, string>}
     */
    public function listProducts(array $query): array
    {
        $filters = $this->productFilters($query);
        $search = $filters['search'];
        $status = $filters['status'];
        $sortBy = $filters['sort_by'];
        $sortDir = $filters['sort_dir'];

        $sortColumns = $this->productSortColumns();

        $apiParams = [];
        if ($search !== '') {
            $apiParams['q'] = $search;
        }
        if ($status !== 'all') {
            $apiParams['status'] = $status;
        }

        $products = array_map(self::mapProductSummary(...), $this->cachedProducts($apiParams));

        $column = $sortColumns[$sortBy];
        usort($products, function (array $a, array $b) use ($column, $sortDir) {
            $result = strcasecmp((string) ($a[$column] ?? ''), (string) ($b[$column] ?? ''));

            return $sortDir === 'desc' ? -$result : $result;
        });

        return [
            'products' => $products,
            'filters' => $filters,
        ];
    }

    /**
     * Normalize product-list filters without contacting the inventory API.
     *
     * @param  array<string, mixed>  $query
     * @return array{search: string, status: string, sort_by: string, sort_dir: string}
     */
    public function productFilters(array $query): array
    {
        $search = trim((string) ($query['search'] ?? ''));
        $status = strtolower(trim((string) ($query['status'] ?? 'active'))) ?: 'active';
        $sortBy = strtolower(trim((string) ($query['sort_by'] ?? 'brand'))) ?: 'brand';
        $sortDir = strtolower(trim((string) ($query['sort_dir'] ?? 'asc'))) ?: 'asc';

        $sortColumns = $this->productSortColumns();
        if (! array_key_exists($sortBy, $sortColumns)) {
            $sortBy = 'brand';
        }
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'asc';
        }
        if (! in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }

        return [
            'search' => $search,
            'status' => $status,
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
        ];
    }

    /** @return array<string, string> */
    private function productSortColumns(): array
    {
        return [
            'generic' => 'generic_name',
            'brand' => 'product_name',
            'category' => 'category',
            'unit' => 'unit',
            'dosage' => 'dosage',
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>, ok: bool}
     */
    public function request(array $params = [], string $endpoint = '/products'): array
    {
        $response = Http::baseUrl(config('services.inventory_api.base_url'))
            ->withToken(config('services.inventory_api.token'))
            ->timeout(15)
            ->get($endpoint, $params)
            ->throw();

        return $response->json();
    }

    /**
     * Fetch every page while preserving the API's page order. The first page
     * establishes the filtered total; remaining pages can then be requested
     * concurrently instead of waiting for each network round trip in turn.
     *
     * @param  array<string, mixed>  $params
     * @return array<int, array<string, mixed>>
     */
    private function allResources(array $params, string $endpoint): array
    {
        $firstPage = $this->request($params + [
            'page' => 1,
            'limit' => self::MAX_PAGE_SIZE,
        ], $endpoint);

        $rows = $firstPage['data'] ?? [];
        $total = $firstPage['meta']['total'] ?? null;

        if (! is_numeric($total)) {
            return $this->finishSequentially($rows, $firstPage, $params, $endpoint);
        }

        $lastPage = (int) ceil(max((int) $total, count($rows)) / self::MAX_PAGE_SIZE);
        if ($lastPage <= 1) {
            return $rows;
        }

        $responses = Http::pool(function (Pool $pool) use ($endpoint, $lastPage, $params) {
            for ($page = 2; $page <= $lastPage; $page++) {
                $pool->as((string) $page)
                    ->withToken(config('services.inventory_api.token'))
                    ->timeout(15)
                    ->get($this->endpointUrl($endpoint), $params + [
                        'page' => $page,
                        'limit' => self::MAX_PAGE_SIZE,
                    ]);
            }
        }, self::MAX_CONCURRENT_REQUESTS);

        for ($page = 2; $page <= $lastPage; $page++) {
            $response = $responses[(string) $page] ?? null;

            if ($response instanceof Throwable) {
                throw $response;
            }
            if (! $response instanceof Response) {
                throw new RuntimeException("Inventory API page {$page} returned no response.");
            }

            $payload = $response->throw()->json();
            $rows = array_merge($rows, $payload['data'] ?? []);
        }

        return $rows;
    }

    /**
     * Retain compatibility with an upstream response that omits `meta.total`.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $firstPage
     * @param  array<string, mixed>  $params
     * @return array<int, array<string, mixed>>
     */
    private function finishSequentially(array $rows, array $firstPage, array $params, string $endpoint): array
    {
        $page = 2;
        $hasNext = (bool) ($firstPage['meta']['has_next'] ?? false);

        while ($hasNext) {
            $response = $this->request($params + [
                'page' => $page,
                'limit' => self::MAX_PAGE_SIZE,
            ], $endpoint);

            $rows = array_merge($rows, $response['data'] ?? []);
            $hasNext = (bool) ($response['meta']['has_next'] ?? false);
            $page++;
        }

        return $rows;
    }

    private function endpointUrl(string $endpoint): string
    {
        return rtrim((string) config('services.inventory_api.base_url'), '/').'/'.ltrim($endpoint, '/');
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

    /**
     * Map only the fields used by the admin product catalog.
     *
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    public static function mapProductSummary(array $product): array
    {
        return [
            'id' => $product['id'],
            'sku' => $product['sku'],
            'product_name' => $product['product_name'] ?? $product['name'],
            'category' => $product['category'],
            'generic_name' => $product['generic'],
            'dosage' => $product['dosage'],
            'unit' => $product['unit_type'],
            'unit_price' => $product['current_price'],
            'is_active' => (bool) $product['is_active'],
        ];
    }

    /**
     * Maps one raw API customer row onto the field names customers:sync
     * upserts into the local `customers` table.
     *
     * @param  array<string, mixed>  $customer
     * @return array<string, mixed>
     */
    public static function mapCustomer(array $customer): array
    {
        return [
            'external_id' => $customer['id'],
            'company_name' => $customer['name'],
            'channel' => $customer['channel'],
        ];
    }
}
