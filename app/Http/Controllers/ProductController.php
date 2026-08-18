<?php

namespace App\Http\Controllers;

use App\Support\InventoryApiClient;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Products are read-only here, sourced live from the inventoryapp
 * catalog API instead of a local table -- see InventoryApiClient.
 */
class ProductController extends Controller
{
    public function __construct(private readonly InventoryApiClient $inventory) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = strtolower(trim((string) $request->query('status', 'active'))) ?: 'active';
        $sortBy = strtolower(trim((string) $request->query('sort_by', 'brand'))) ?: 'brand';
        $sortDir = strtolower(trim((string) $request->query('sort_dir', 'asc'))) ?: 'asc';

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

        $products = array_map(
            InventoryApiClient::mapProduct(...),
            $this->inventory->allProducts($apiParams),
        );

        $column = $sortColumns[$sortBy];
        usort($products, function (array $a, array $b) use ($column, $sortDir) {
            $result = strcasecmp((string) ($a[$column] ?? ''), (string) ($b[$column] ?? ''));

            return $sortDir === 'desc' ? -$result : $result;
        });

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
        ]);
    }
}
