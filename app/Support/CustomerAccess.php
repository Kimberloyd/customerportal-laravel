<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CustomerAccess
{
    public static function applyToCustomers(Builder $query, User $user): Builder
    {
        if ($user->role === User::ROLE_CUSTOMER) {
            $customer = CustomerScope::activeCustomerFor($user);

            return $customer ? $query->whereKey($customer->id) : $query->whereRaw('1 = 0');
        }

        if ($user->role === User::ROLE_AGENT) {
            // An agent sees a customer once it's assigned to them or to a
            // teammate (same team_members team), plus anything nobody has
            // been assigned yet -- unassigned customers stay reachable by
            // every agent rather than disappearing until an admin assigns one.
            $employeeIds = self::teamEmployeeIds($user);

            return $query->where(function (Builder $q) use ($employeeIds) {
                $q->whereNull('assigned_employee_id')
                    ->orWhereIn('assigned_employee_id', $employeeIds);
            });
        }

        return in_array($user->role, [User::ROLE_ADMIN, User::ROLE_OFFICE], true)
            ? $query
            : $query->whereRaw('1 = 0');
    }

    public static function applyToOrders(Builder $query, User $user): Builder
    {
        if ($user->role === User::ROLE_CUSTOMER) {
            $customer = CustomerScope::activeCustomerFor($user);

            return $customer ? $query->where('customer_id', $customer->id) : $query->whereRaw('1 = 0');
        }

        if ($user->role === User::ROLE_AGENT) {
            return $query->whereIn('customer_id', self::applyToCustomers(Customer::query(), $user)->select('customers.id'));
        }

        return in_array($user->role, [User::ROLE_ADMIN, User::ROLE_OFFICE], true)
            ? $query
            : $query->whereRaw('1 = 0');
    }

    /**
     * The agent's own id plus every other user sharing a team with them.
     * team_members has a unique constraint on user_id, so an agent belongs
     * to at most one team -- but this makes no assumption about that beyond
     * reading whichever team row(s) actually exist.
     *
     * @return array<int, int>
     */
    private static function teamEmployeeIds(User $user): array
    {
        $teamIds = DB::table('team_members')->where('user_id', $user->id)->pluck('team_id');

        if ($teamIds->isEmpty()) {
            return [$user->id];
        }

        return DB::table('team_members')
            ->whereIn('team_id', $teamIds)
            ->pluck('user_id')
            ->push($user->id)
            ->unique()
            ->values()
            ->all();
    }

    public static function canAccess(Customer $customer, User $user): bool
    {
        return self::applyToCustomers(Customer::query(), $user)->whereKey($customer->id)->exists();
    }

    public static function customerIdsFor(User $user)
    {
        return self::applyToCustomers(Customer::query(), $user)->select('customers.id');
    }

    public static function staffRecipientIdsForCustomer(?int $customerId): array
    {
        return User::query()
            ->where('is_active', true)
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_OFFICE, User::ROLE_AGENT])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
