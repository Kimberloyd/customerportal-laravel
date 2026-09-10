<?php
namespace App\Policies;
use App\Models\ProductReturn;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\CustomerAccess;
use App\Support\CustomerScope;
class ProductReturnPolicy
{
    public function create(User $user, PurchaseOrder $order): bool { return $user->role === User::ROLE_CUSTOMER && CustomerScope::activeCustomerFor($user)?->id === $order->customer_id; }
    public function update(User $user, ProductReturn $return): bool { return in_array($user->role, User::STAFF_ROLES, true) && CustomerAccess::applyToOrders(PurchaseOrder::query(), $user)->whereKey($return->purchase_order_id)->exists(); }
    public function viewAttachment(User $user, ProductReturn $return): bool { return CustomerAccess::applyToOrders(PurchaseOrder::query(), $user)->whereKey($return->purchase_order_id)->exists(); }
}
