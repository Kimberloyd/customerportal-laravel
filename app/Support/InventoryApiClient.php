<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

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
     * Fetch every customer, paging through the API's max page size (100)
     * until `has_next` is false.
     *
     * @param  array<string, mixed>  $params  Extra query params.
     * @return array<int, array<string, mixed>>
     */
    public function allCustomers(array $params = []): array
    {
        $customers = [];
        $page = 1;

        do {
            $response = $this->request($params + [
                'page' => $page,
                'limit' => self::MAX_PAGE_SIZE,
            ], '/customers');

            $customers = array_merge($customers, $response['data'] ?? []);
            $hasNext = (bool) ($response['meta']['has_next'] ?? false);
            $page++;
        } while ($hasNext);

        return $customers;
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
        $search = trim((string) ($query['search'] ?? ''));
        $status = strtolower(trim((string) ($query['status'] ?? 'active'))) ?: 'active';
        $sortBy = strtolower(trim((string) ($query['sort_by'] ?? 'brand'))) ?: 'brand';
        $sortDir = strtolower(trim((string) ($query['sort_dir'] ?? 'asc'))) ?: 'asc';

        $sortColumns = [
            'generic' => 'generic_name',
            'brand' => 'product_name',
            'category' => 'category',
            'unit' => 'unit',
            'description' => 'description',
            'dosage' => 'dosage',
        ];
        if (! array_key_exists($sortBy, $sortColumns)) {
            $sortBy = 'brand';
        }
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'asc';
        }
        if (! in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }

        $apiParams = [];
        if ($search !== '') {
            $apiParams['q'] = $search;
        }
        if ($status !== 'all') {
            $apiParams['status'] = $status;
        }

        $products = array_map(self::mapProduct(...), $this->allProducts($apiParams));

        $column = $sortColumns[$sortBy];
        usort($products, function (array $a, array $b) use ($column, $sortDir) {
            $result = strcasecmp((string) ($a[$column] ?? ''), (string) ($b[$column] ?? ''));

            return $sortDir === 'desc' ? -$result : $result;
        });

        return [
            'products' => $products,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
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
