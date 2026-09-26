<?php

namespace App\Services;

use App\Models\Garment;
use App\Models\GarmentCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GarmentService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Garment::query()->with('category');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhere('condition_note', 'like', "%{$search}%")
                  ->orWhereHas('category', function ($sub) use ($search) {
                      $sub->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by garment_category_id (new) or category (legacy)
        if (!empty($filters['category'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('garment_category_id', $filters['category'])
                  ->orWhere('category', $filters['category']);
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
            'price_asc' => ['price', 'asc'],
            'price_desc' => ['price', 'desc'],
        ];
        $sort = $filters['sort'] ?? 'latest';
        [$sortBy, $sortOrder] = $sortMap[$sort] ?? ['created_at', 'desc'];

        return $query->orderBy($sortBy, $sortOrder)->paginate(10);
    }

    public function find(int $id): ?Garment
    {
        return Garment::withTrashed()->with('category')->find($id);
    }

    public function create(array $data): Garment
    {
        return Garment::create($data);
    }

    public function update(Garment $garment, array $data): Garment
    {
        $garment->update($data);
        return $garment->fresh('category');
    }

    public function delete(Garment $garment): bool
    {
        return $garment->delete();
    }

    public function restore(int $id): ?Garment
    {
        $garment = Garment::onlyTrashed()->find($id);
        if ($garment) {
            $garment->restore();
        }
        return $garment;
    }

    /**
     * Get categories for dropdown - uses new GarmentCategory model if available,
     * falls back to legacy category field.
     */
    public function getCategories(): array
    {
        // First try to get from GarmentCategory model
        $categories = GarmentCategory::where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        // If no categories in new model, fall back to legacy
        if (empty($categories)) {
            return Garment::whereNotNull('category')
                ->where('status', 'active')
                ->pluck('category')
                ->unique()
                ->sort()
                ->values()
                ->toArray();
        }

        // Return array of names for backward compatibility
        return array_values($categories);
    }

    /**
     * Get categories as key-value pairs for form dropdown
     * Returns ['id' => 'name'] for GarmentCategory model
     */
    public function getCategoryOptions(): array
    {
        $categories = GarmentCategory::where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($categories->isEmpty()) {
            // Fallback to legacy
            $legacyCategories = Garment::whereNotNull('category')
                ->where('status', 'active')
                ->pluck('category')
                ->unique()
                ->sort()
                ->values()
                ->toArray();

            return array_combine($legacyCategories, $legacyCategories);
        }

        return $categories->pluck('name', 'id')->toArray();
    }
}