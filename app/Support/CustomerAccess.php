<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CustomerAccess
{
    public static function applyToCustomers(Builder $query, User $user): Builder
    {
        if ($user->role === User::ROLE_CUSTOMER) {
            $customer = CustomerScope::activeCustomerFor($user);

            return $customer ? $query->whereKey($customer->id) : $query->whereRaw('1 = 0');
        }

        return in_array($user->role, [User::ROLE_ADMIN, User::ROLE_OFFICE, User::ROLE_AGENT], true)
            ? $query
            : $query->whereRaw('1 = 0');
    }

    public static function applyToOrders(Builder $query, User $user): Builder
    {
        if ($user->role === User::ROLE_CUSTOMER) {
            $customer = CustomerScope::activeCustomerFor($user);

            return $customer ? $query->where('customer_id', $customer->id) : $query->whereRaw('1 = 0');
        }

        return in_array($user->role, [User::ROLE_ADMIN, User::ROLE_OFFICE, User::ROLE_AGENT], true)
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
        return User::query()
            ->where('is_active', true)
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_OFFICE, User::ROLE_AGENT])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
