<?php

namespace App\Services;

use App\Models\GarmentCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class GarmentCategoryService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = GarmentCategory::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $allowedSorts = ['id', 'name', 'sort_order', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts) ? $filters['sort_by'] : 'sort_order';
        $sortOrder = ($filters['sort_order'] ?? 'asc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortOrder)->paginate(10);
    }

    public function find(int $id): ?GarmentCategory
    {
        return GarmentCategory::withTrashed()->find($id);
    }

    public function create(array $data): GarmentCategory
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return GarmentCategory::create($data);
    }

    public function update(GarmentCategory $category, array $data): GarmentCategory
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category->update($data);
        return $category->fresh();
    }

    public function delete(GarmentCategory $category): bool
    {
        return $category->delete();
    }

    public function restore(int $id): ?GarmentCategory
    {
        $category = GarmentCategory::onlyTrashed()->find($id);
        if ($category) {
            $category->restore();
        }
        return $category;
    }

    public function getActiveCategories(): \Illuminate\Database\Eloquent\Collection
    {
        return GarmentCategory::where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}