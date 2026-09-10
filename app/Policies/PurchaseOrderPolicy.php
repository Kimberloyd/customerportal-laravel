<?php
namespace App\Policies;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Support\CustomerAccess;
class PurchaseOrderPolicy
{
    public function view(User $user, PurchaseOrder $order): bool { return CustomerAccess::applyToOrders(PurchaseOrder::query(), $user)->whereKey($order->id)->exists(); }
    public function update(User $user, PurchaseOrder $order): bool { return $this->view($user, $order); }
    public function cancel(User $user, PurchaseOrder $order): bool { return $this->view($user, $order); }
    public function viewMessageLog(User $user, PurchaseOrder $order): bool { return in_array($user->role, User::STAFF_ROLES, true) && $this->view($user, $order); }
    public function receive(User $user, PurchaseOrder $order): bool { return in_array($user->role, User::STAFF_ROLES, true) && $this->view($user, $order); }
    public function complete(User $user, PurchaseOrder $order): bool { return $user->role === User::ROLE_CUSTOMER && $this->view($user, $order); }
    public function confirmReceived(User $user, PurchaseOrder $order): bool { return $this->complete($user, $order); }
    public function delete(User $user, PurchaseOrder $order): bool { return $user->role === User::ROLE_ADMIN; }
}
