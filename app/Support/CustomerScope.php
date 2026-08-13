<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Matches Flask's app/customer_scope.py: a customer-role user is scoped
 * to exactly one active linked Customer row, resolved fresh each time
 * (no request-wide memoization needed here -- Eloquent already caches
 * the underlying query per unique call via query result, and this is
 * cheap enough not to bother with Flask's `g`-based memoization).
 */
class CustomerScope
{
    public static function activeCustomerFor(?User $user): ?Customer
    {
        if (! $user || ! $user->is_active || $user->role !== 'customer') {
            return null;
        }

        $linkedCustomers = Customer::where('user_id', $user->id)->limit(2)->get();
        if ($linkedCustomers->count() !== 1) {
            return null;
        }

        $customer = $linkedCustomers->first();

        return $customer->is_active ? $customer : null;
    }

    /**
     * @throws HttpException when $required and no valid scope resolves
     */
    public static function forCurrentUser(bool $required = true): ?Customer
    {
        $user = Auth::user();

        if (! $user || $user->role !== 'customer') {
            return null;
        }

        $customer = self::activeCustomerFor($user);

        if ($customer === null && $required) {
            abort(403, 'This customer login is not linked to one active customer account.');
        }

        return $customer;
    }
}
