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

        $sortMap = [
            'created_at_desc' => ['created_at', 'desc'],
            'created_at_asc' => ['created_at', 'asc'],
            'name_asc' => ['name', 'asc'],
            'name_desc' => ['name', 'desc'],
            'email_asc' => ['email', 'asc'],
            'email_desc' => ['email', 'desc'],
        ];
        $sort = $filters['sort'] ?? 'latest';
        [$sortBy, $sortOrder] = $sortMap[$sort] ?? ['created_at', 'desc'];

        return $query->orderBy($sortBy, $sortOrder)->paginate(10);
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
