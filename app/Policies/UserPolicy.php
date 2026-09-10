<?php
namespace App\Policies;
use App\Models\User;
class UserPolicy
{
    public function manage(User $actor, User $target): bool { return $actor->role === User::ROLE_ADMIN; }
}
