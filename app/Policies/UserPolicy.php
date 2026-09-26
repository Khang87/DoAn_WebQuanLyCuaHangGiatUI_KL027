<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isManager();
    }

    public function view(User $user, User $target): bool
    {
        return $user->isManager() || $user->id === $target->id;
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    public function update(User $user, User $target): bool
    {
        return $user->isManager() || $user->id === $target->id;
    }

    public function delete(User $user, User $target): bool
    {
        return $user->isManager();
    }

    public function restore(User $user, User $target): bool
    {
        return $user->isManager();
    }

    public function forceDelete(User $user, User $target): bool
    {
        return $user->isManager();
    }
}
