<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\InventoryApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly InventoryApiClient $inventory) {}

    public function index(Request $request): Response
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        $tab = $request->query('tab', 'products');

        return Inertia::render('Admin/Dashboard', [
            'activeTab' => $tab,
            ...match ($tab) {
                'customers' => $this->listCustomers($request->query()),
                default => $this->inventory->listProducts($request->query()),
            },
        ]);
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

        return [
            'customers' => $customerQuery->orderByDesc('created_at')->paginate(10)->withQueryString(),
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ];
    }
}
