<?php

namespace App\Services;

use App\Models\LaundryCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LaundryCategoryService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = LaundryCategory::query();

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $numericPart = preg_replace('/[^0-9]/', '', $search);
                if (!empty($numericPart)) {
                    $q->where('id', $numericPart);
                }
                $q->orWhere('code', 'LIKE', "%{$search}%")
                    ->orWhere('name', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $sortMap = [
            'created_at_desc' => ['created_at', 'desc'],
            'created_at_asc' => ['created_at', 'asc'],
            'name_asc' => ['name', 'asc'],
            'name_desc' => ['name', 'desc'],
        ];
        $sort = $filters['sort'] ?? 'created_at_desc';
        [$sortBy, $sortOrder] = $sortMap[$sort] ?? ['created_at', 'desc'];

        return $query->orderBy($sortBy, $sortOrder)->paginate(10)->withQueryString();
    }

    public function find(int $id): ?LaundryCategory
    {
        return LaundryCategory::withTrashed()->find($id);
    }

    public function create(array $data): LaundryCategory
    {
        if (empty($data['slug'])) {
            $data['slug'] = \Str::slug($data['name']);
        }
        if (empty($data['status'])) {
            $data['status'] = 'active';
        }
        if (empty($data['code'])) {
            $data['code'] = 'DM' . str_pad(LaundryCategory::max('id') + 1, 3, '0', STR_PAD_LEFT);
        }
        return LaundryCategory::create($data);
    }

    public function update(LaundryCategory $category, array $data): LaundryCategory
    {
        $category->update($data);
        return $category->fresh();
    }

    public function delete(LaundryCategory $category): bool
    {
        return $category->delete();
    }

    public function restore(int $id): ?LaundryCategory
    {
        $category = LaundryCategory::onlyTrashed()->find($id);
        if ($category) {
            $category->restore();
        }
        return $category;
    }
}