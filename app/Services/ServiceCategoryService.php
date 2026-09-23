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
            $search = $filters['search'];
            $query->where('name', 'like', "%{$search}%");
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->withTrashed()->latest()->paginate(20);
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
