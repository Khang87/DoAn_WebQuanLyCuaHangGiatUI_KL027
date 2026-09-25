<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function view(User $user, User $target): bool
    {
        return $user->role === 'admin' || $user->id === $target->id;
    }

    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user, User $target): bool
    {
        return $user->role === 'admin' || $user->id === $target->id;
    }

    public function delete(User $user, User $target): bool
    {
        return $user->role === 'admin';
    }

    public function restore(User $user, User $target): bool
    {
        return $user->role === 'admin';
    }

    public function forceDelete(User $user, User $target): bool
    {
        return $user->role === 'admin';
    }
}
