<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\AdminUserListing;
use App\Support\InventoryApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly InventoryApiClient $inventory,
        private readonly AdminUserListing $userListing,
    ) {}

    public function index(Request $request): Response
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        $tab = $request->query('tab', 'products');

        return Inertia::render('Admin/Dashboard', [
            'activeTab' => $tab,
            ...match ($tab) {
                'customers' => $this->listCustomers($request->query()),
                'accounts' => $this->listAccounts($request->query()),
                default => $this->listProducts($request->query()),
            },
        ]);
    }

    /**
     * Defer the complete status-filtered catalog so the Admin shell renders
     * immediately and searching/pagination can happen locally afterward.
     *
     * @param  array<string, mixed>  $query
     * @return array{products: mixed, filters: array<string, string>}
     */
    private function listProducts(array $query): array
    {
        $filters = $this->inventory->productFilters($query);
        $catalogQuery = [...$filters, 'search' => ''];

        return [
            'products' => Inertia::defer(
                fn () => $this->inventory->listProducts($catalogQuery)['products'],
                'catalog',
            ),
            'filters' => $filters,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{customers: mixed, filters: array<string, string>}
     */
    private function listCustomers(array $query): array
    {
        $search = trim((string) ($query['search'] ?? ''));
        $status = strtolower(trim((string) ($query['status'] ?? 'active'))) ?: 'active';
        if (! in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }

        return [
            'customers' => Inertia::defer(
                fn () => $this->customers($search, $status),
                'customers',
            ),
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function listAccounts(array $query): array
    {
        return [
            'users' => Inertia::defer(
                fn () => $this->userListing->users($query),
                'accounts',
            ),
            'filters' => $this->userListing->filters($query),
            'roleLabels' => AdminUserListing::ROLE_LABELS,
            'accountForm' => [
                'allowAdminCreation' => false,
                'customers' => Customer::where('is_active', true)
                    ->orderBy('company_name')
                    ->get(['id', 'company_name', 'user_id']),
            ],
        ];
    }

    private function customers(string $search, string $status)
    {
        $customerQuery = Customer::query();

        if ($status === 'active') {
            $customerQuery->where('is_active', true);
        } elseif ($status === 'inactive') {
            $customerQuery->where('is_active', false);
        }

        if ($search !== '') {
            $pattern = '%'.strtolower($search).'%';
            $customerQuery->where(function ($q) use ($pattern) {
                $q->whereRaw('LOWER(customer_code) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(company_name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(channel, \'\')) LIKE ?', [$pattern]);
            });
        }

        return $customerQuery->orderByDesc('created_at')->paginate(10)->withQueryString();
    }
}
