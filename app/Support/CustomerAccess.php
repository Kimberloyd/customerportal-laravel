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
            $query->whereIn('customers.assigned_employee_id', self::agentTeamMemberIds($user));

            return $query;
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
            $query->whereIn('customer_id', self::customerIdsFor($user));

            return $query;
        }

        return in_array($user->role, [User::ROLE_ADMIN, User::ROLE_OFFICE], true)
            ? $query
            : $query->whereRaw('1 = 0');
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
        $recipientIds = User::query()
            ->where('is_active', true)
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_OFFICE])
            ->pluck('id');

        if ($customerId !== null) {
            $assignedAgentId = Customer::query()->whereKey($customerId)->value('assigned_employee_id');

            if ($assignedAgentId) {
                $agent = User::query()->find($assignedAgentId);
                if ($agent) {
                    $recipientIds = $recipientIds->merge(self::agentTeamMemberIds($agent)->pluck('id'));
                }
            }
        }

        return $recipientIds->unique()->map(fn ($id) => (int) $id)->values()->all();
    }

    private static function agentTeamMemberIds(User $user): Builder
    {
        $teamIds = DB::table('team_members')
            ->where('user_id', $user->id)
            ->select('team_id');

        return User::query()
            ->select('users.id')
            ->where('is_active', true)
            ->where('role', User::ROLE_AGENT)
            ->where(function (Builder $query) use ($user, $teamIds): void {
                $query->whereKey($user->id)
                    ->orWhereIn('id', DB::table('team_members')->whereIn('team_id', $teamIds)->select('user_id'));
            });
    }
}
