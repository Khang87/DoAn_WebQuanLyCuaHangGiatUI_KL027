<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->canPermission('accounts.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->canPermission('accounts.view') || $user->getKey() === $target->getKey();
    }

    public function create(User $user): bool
    {
        return $user->canPermission('accounts.create');
    }

    public function update(User $user, User $target): bool
    {
        return $user->canPermission('accounts.edit') || $user->getKey() === $target->getKey();
    }

    public function delete(User $user, User $target): bool
    {
        return $user->canPermission('accounts.delete');
    }

    public function restore(User $user, User $target): bool
    {
        return $user->canPermission('accounts.edit');
    }

    public function forceDelete(User $user, User $target): bool
    {
        return $user->canPermission('accounts.delete');
    }
}
