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
    public function updatePoNumber(User $user, PurchaseOrder $order): bool { return in_array($user->role, User::STAFF_ROLES, true) && $this->view($user, $order); }
    public function receive(User $user, PurchaseOrder $order): bool { return in_array($user->role, User::STAFF_ROLES, true) && $this->view($user, $order); }
    public function complete(User $user, PurchaseOrder $order): bool { return $user->role === User::ROLE_CUSTOMER && $this->view($user, $order); }
    public function confirmReceived(User $user, PurchaseOrder $order): bool { return $this->complete($user, $order); }
    public function delete(User $user, PurchaseOrder $order): bool { return $user->role === User::ROLE_ADMIN || ($user->role === User::ROLE_AGENT && $this->view($user, $order)); }
    // Archive management (viewing the archive list, restoring, permanently
    // deleting) is admin-only -- narrower than delete() above, since a
    // permanent delete can't be undone the way an archive can. Not routed
    // through view()/CustomerAccess: that scope excludes soft-deleted rows
    // by default, which would make it reject exactly the archived orders
    // these abilities exist to manage.
    public function viewArchive(User $user): bool { return $user->role === User::ROLE_ADMIN; }
    public function restore(User $user, PurchaseOrder $order): bool { return $user->role === User::ROLE_ADMIN; }
    public function forceDelete(User $user, PurchaseOrder $order): bool { return $user->role === User::ROLE_ADMIN; }
}
