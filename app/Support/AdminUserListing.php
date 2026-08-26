<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\User;

final class AdminUserListing
{
    public const ROLE_LABELS = [
        'admin' => 'Admin',
        'employee' => 'Employee',
        'customer' => 'Customer',
    ];

    /**
     * @param  array<string, mixed>  $query
     * @return array{users: mixed, filters: array{search: string, role: string}, roleLabels: array<string, string>}
     */
    public function get(array $query): array
    {
        $search = trim((string) ($query['search'] ?? ''));
        $role = strtolower(trim((string) ($query['role'] ?? 'all'))) ?: 'all';
        if (! array_key_exists($role, self::ROLE_LABELS)) {
            $role = 'all';
        }

        $usersQuery = User::query();

        if ($search !== '') {
            $pattern = '%'.strtolower($search).'%';
            $usersQuery->where(function ($builder) use ($pattern) {
                $builder->whereRaw('LOWER(full_name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$pattern]);
            });
        }

        if ($role !== 'all') {
            $usersQuery->where('role', $role);
        }

        $users = $usersQuery->orderBy('full_name')->paginate(10)->withQueryString();
        $userIds = collect($users->items())->pluck('id');
        $linkedCustomers = Customer::whereIn('user_id', $userIds)->get()->keyBy('user_id');

        $users->through(fn (User $user) => [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'linked_customer_name' => $linkedCustomers->get($user->id)?->company_name,
        ]);

        return [
            'users' => $users,
            'filters' => ['search' => $search, 'role' => $role],
            'roleLabels' => self::ROLE_LABELS,
        ];
    }
}
