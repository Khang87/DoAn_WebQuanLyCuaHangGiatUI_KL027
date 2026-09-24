<?php

namespace App\Services;

use App\Models\ServiceCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ServiceCategoryService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = ServiceCategory::query();

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $numericPart = preg_replace('/[^0-9]/', '', $search);
                if (!empty($numericPart)) {
                    $q->where('id', $numericPart);
                }
                $q->orWhere('slug', 'LIKE', "%{$search}%")
                    ->orWhere('name', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->withTrashed()->latest()->paginate(10)->withQueryString();
    }

    public function find(int $id): ?ServiceCategory
    {
        return ServiceCategory::withTrashed()->find($id);
    }

    public function create(array $data): ServiceCategory
    {
        if (empty($data['slug'])) {
            $data['slug'] = \Str::slug($data['name']);
        }
        if (empty($data['status'])) {
            $data['status'] = 'active';
        }
        return ServiceCategory::create($data);
    }

    public function update(ServiceCategory $category, array $data): ServiceCategory
    {
        $category->update($data);
        return $category->fresh();
    }

    public function delete(ServiceCategory $category): bool
    {
        return $category->delete();
    }

    public function restore(int $id): ?ServiceCategory
    {
        $category = ServiceCategory::onlyTrashed()->find($id);
        if ($category) {
            $category->restore();
        }
        return $category;
    }
}
