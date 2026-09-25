<?php

namespace App\Services;

use App\Models\Garment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GarmentService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Garment::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhere('condition_note', 'like', "%{$search}%");
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $allowedSorts = ['id', 'name', 'category', 'price', 'status', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts) ? $filters['sort_by'] : 'created_at';
        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->withTrashed()->orderBy($sortBy, $sortOrder)->paginate(20);
    }

    public function find(int $id): ?Garment
    {
        return Garment::withTrashed()->find($id);
    }

    public function create(array $data): Garment
    {
        return Garment::create($data);
    }

    public function update(Garment $garment, array $data): Garment
    {
        $garment->update($data);
        return $garment->fresh();
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

    public function getCategories(): array
    {
        return Garment::whereNotNull('category')->where('status', 'active')->pluck('category')->unique()->sort()->values()->toArray();
    }
}
