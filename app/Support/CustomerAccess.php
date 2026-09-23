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
            // An agent sees a customer only once it's assigned to them or to
            // a teammate (same team_members team). Unassigned customers stay
            // invisible here until an admin/office assigns one -- separately,
            // applyToClaimableCustomers() below deliberately still includes
            // unassigned customers, for the places where claiming one for the
            // first time (by creating an account or an order for them) is the
            // actual point.
            return $query->whereIn('assigned_employee_id', self::teamEmployeeIds($user));
        }

        return in_array($user->role, [User::ROLE_ADMIN, User::ROLE_OFFICE], true)
            ? $query
            : $query->whereRaw('1 = 0');
    }

    /**
     * Same as applyToCustomers(), except an agent also sees a customer
     * nobody has claimed yet. Use this instead of applyToCustomers() only
     * where creating something for a customer is itself how that customer
     * gets assigned (a new login account, a new order) -- everywhere else
     * (browsing, messaging, reports) should keep unassigned customers out
     * of an agent's view.
     */
    public static function applyToClaimableCustomers(Builder $query, User $user): Builder
    {
        if ($user->role !== User::ROLE_AGENT) {
            return self::applyToCustomers($query, $user);
        }

        $employeeIds = self::teamEmployeeIds($user);

        return $query->where(function (Builder $q) use ($employeeIds) {
            $q->whereNull('assigned_employee_id')
                ->orWhereIn('assigned_employee_id', $employeeIds);
        });
    }

    /**
     * If this customer isn't assigned to anyone yet and the acting user is
     * an agent, assign it to them -- called wherever an agent creates
     * something (an order, a login account) for a customer, so the
     * customer they just acted on doesn't stay invisible to them
     * afterward (applyToCustomers() above hides unassigned customers).
     * Locks the row: two agents racing to claim the same unassigned
     * customer must not both succeed.
     */
    public static function claimIfUnassigned(Customer $customer, User $actor): void
    {
        if ($actor->role !== User::ROLE_AGENT) {
            return;
        }

        $locked = Customer::query()->lockForUpdate()->find($customer->id);
        if ($locked && ! $locked->assigned_employee_id) {
            $locked->update(['assigned_employee_id' => $actor->id]);
            $customer->assigned_employee_id = $locked->assigned_employee_id;
        }
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
     * reading whichever team row(s) actually exist. Public: also used by
     * AgentCustomerAccountController, which has its own two customer
     * queries (unassigned + assigned) rather than going through
     * applyToCustomers()/applyToOrders() above.
     *
     * @return array<int, int>
     */
    public static function teamEmployeeIds(User $user): array
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
