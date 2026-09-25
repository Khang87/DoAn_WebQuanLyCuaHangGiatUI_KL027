<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function getAll(array $filters = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = User::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
        }

        if (!empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $query->whereNull('deleted_at');
            } elseif ($filters['status'] === 'inactive') {
                $query->whereNotNull('deleted_at');
            }
        }

        $allowedSorts = ['id', 'name', 'email', 'phone', 'role', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts) ? $filters['sort_by'] : 'created_at';
        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->withTrashed()->orderBy($sortBy, $sortOrder)->paginate(20);
    }

    public function find(int $id): ?User
    {
        return User::withTrashed()->find($id);
    }

    public function create(array $data): User
    {
        $data['password'] = Hash::make($data['password']);
        if (empty($data['role'])) {
            $data['role'] = 'customer';
        }
        return User::create($data);
    }

    public function update(User $user, array $data): User
    {
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $user->update($data);
        return $user->fresh();
    }

    public function delete(User $user): bool
    {
        if ($user->id === auth()->id()) {
            return false;
        }
        return $user->delete();
    }

    public function restore(int $id): ?User
    {
        $user = User::onlyTrashed()->find($id);
        if ($user) {
            $user->restore();
        }
        return $user;
    }

    public function forceDelete(int $id): bool
    {
        $user = User::onlyTrashed()->find($id);
        if ($user) {
            return $user->forceDelete();
        }
        return false;
    }
}
