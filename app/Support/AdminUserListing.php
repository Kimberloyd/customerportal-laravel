<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

final class AdminUserListing
{
    public const ROLE_LABELS = [
        'admin' => 'Admin',
        'employee' => 'Employee',
        'customer' => 'Customer',
    ];

    /**
     * @param  array<string, mixed>  $query
     * @return array{users: mixed, filters: array{search: string, role: string, retention_days: int}, roleLabels: array<string, string>}
     */
    public function get(array $query): array
    {
        return [
            'users' => $this->users($query),
            'filters' => $this->filters($query),
            'roleLabels' => self::ROLE_LABELS,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{search: string, role: string, retention_days: int}
     */
    public function filters(array $query): array
    {
        $search = trim((string) ($query['search'] ?? ''));
        $role = strtolower(trim((string) ($query['role'] ?? 'all'))) ?: 'all';
        if (! array_key_exists($role, self::ROLE_LABELS)) {
            $role = 'all';
        }

        return [
            'search' => $search,
            'role' => $role,
            'retention_days' => max(1, (int) config('account-deletion.retention_days')),
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function users(array $query)
    {
        $filters = $this->filters($query);
        $search = $filters['search'];
        $role = $filters['role'];

        // Pending-deletion accounts stay visible to administrators during the
        // retention window so the deletion can be cancelled before purge.
        $usersQuery = User::withTrashed();

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

        $users = $usersQuery
            ->select([
                'id', 'full_name', 'email', 'phone', 'role', 'is_active',
                'deleted_at', 'deactivated_at', 'purge_after',
            ])
            ->orderBy('full_name')
            ->paginate(10)
            ->withQueryString();
        $userIds = collect($users->items())->pluck('id');
        $linkedCustomers = Customer::whereIn('user_id', $userIds)
            ->get(['id', 'user_id', 'company_name'])
            ->keyBy('user_id');
        $currentUserId = Auth::id();

        $users->through(fn (User $user) => [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'deleted_at' => $user->deleted_at?->toIso8601String(),
            'deactivated_at' => $user->deactivated_at?->toIso8601String(),
            'purge_after' => $user->purge_after?->toIso8601String(),
            'is_self' => $user->id === $currentUserId,
            'linked_customer_id' => $linkedCustomers->get($user->id)?->id,
            'linked_customer_name' => $linkedCustomers->get($user->id)?->company_name,
        ]);

        return $users;
    }
}
