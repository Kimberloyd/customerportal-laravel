<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

final class AdminUserListing
{
    public const ROLE_LABELS = [
        'admin' => 'Admin',
        'office' => 'Office',
        'agent' => 'Agent',
        'customer' => 'Customer',
    ];

    /**
     * @param  array<string, mixed>  $query
     * @return array{customers: mixed, staff: mixed, filters: array{search: string, retention_days: int}, roleLabels: array<string, string>}
     */
    public function get(array $query): array
    {
        return [
            'customers' => $this->customerUsers($query),
            'staff' => $this->staffUsers($query),
            'filters' => $this->filters($query),
            'roleLabels' => self::ROLE_LABELS,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{search: string, retention_days: int}
     */
    public function filters(array $query): array
    {
        return [
            'search' => trim((string) ($query['search'] ?? '')),
            'retention_days' => max(1, (int) config('account-deletion.retention_days')),
        ];
    }

    /**
     * The Accounts tab shows customer-role and staff-role (admin/office/
     * agent) accounts as two separate tables rather than one list an admin
     * has to filter by role -- each paginates independently (distinct page
     * query-string keys) so paging through one doesn't reset the other.
     *
     * @param  array<string, mixed>  $query
     */
    public function customerUsers(array $query): LengthAwarePaginator
    {
        return $this->paginate(
            $this->baseQuery($query)->where('role', 'customer'),
            'customer_page',
        );
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function staffUsers(array $query): LengthAwarePaginator
    {
        return $this->paginate(
            $this->baseQuery($query)->where('role', '!=', 'customer'),
            'staff_page',
        );
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function baseQuery(array $query): Builder
    {
        $search = $this->filters($query)['search'];

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

        return $usersQuery->select([
            'id', 'public_id', 'full_name', 'email', 'phone', 'role', 'is_active',
            'deleted_at', 'deactivated_at', 'purge_after',
        ]);
    }

    private function paginate(Builder $usersQuery, string $pageName): LengthAwarePaginator
    {
        $users = $usersQuery->orderBy('full_name')->paginate(10, ['*'], $pageName)->withQueryString();

        $userIds = collect($users->items())->pluck('id');
        $linkedCustomers = Customer::whereIn('user_id', $userIds)
            ->get(['id', 'user_id', 'company_name'])
            ->keyBy('user_id');
        $currentUserId = Auth::id();

        $users->through(fn (User $user) => [
            'id' => $user->id,
            'public_id' => $user->public_id,
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
