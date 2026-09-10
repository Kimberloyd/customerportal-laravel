<?php
namespace App\Policies;
use App\Models\Customer;
use App\Models\User;
use App\Support\CustomerAccess;
class CustomerPolicy
{
    public function view(User $user, Customer $customer): bool { return CustomerAccess::canAccess($customer, $user); }
    public function delete(User $user, Customer $customer): bool { return $user->role === User::ROLE_ADMIN; }
    public function toggleActive(User $user, Customer $customer): bool { return in_array($user->role, [User::ROLE_ADMIN, User::ROLE_OFFICE], true); }
}
