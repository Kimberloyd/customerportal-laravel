<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\CustomerMessage;
use App\Models\User;
use App\Support\CustomerScope;

class CustomerMessagePolicy
{
    public function usePortalThread(User $user, Customer $customer): bool
    {
        if ($user->role === User::ROLE_CUSTOMER) {
            return CustomerScope::activeCustomerFor($user)?->id === $customer->id;
        }

        return in_array($user->role, User::STAFF_ROLES, true)
            && $customer->is_active
            && $customer->user()->where('is_active', true)->where('role', User::ROLE_CUSTOMER)->exists();
    }

    public function manageFacebookThread(User $user, CustomerMessage $thread): bool
    {
        return in_array($user->role, User::STAFF_ROLES, true)
            && $thread->isRoot()
            && $thread->isFacebookMessenger();
    }
}
