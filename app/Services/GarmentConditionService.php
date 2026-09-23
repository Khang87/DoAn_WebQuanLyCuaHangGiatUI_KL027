<?php

namespace App\Services;

use App\Models\GarmentCondition;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GarmentConditionService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = GarmentCondition::query();

        if (!empty($filters['garment_id'])) {
            $query->where('garment_id', $filters['garment_id']);
        }

        if (!empty($filters['condition_type'])) {
            $query->where('condition_type', 'like', "%{$filters['condition_type']}%");
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->with('garment')->withTrashed()->latest()->paginate(20);
    }

    public function find(int $id): ?GarmentCondition
    {
        return GarmentCondition::withTrashed()->find($id);
    }

    public function create(array $data): GarmentCondition
    {
        return GarmentCondition::create($data);
    }

    public function update(GarmentCondition $condition, array $data): GarmentCondition
    {
        $condition->update($data);
        return $condition->fresh();
    }

    public function delete(GarmentCondition $condition): bool
    {
        return $condition->delete();
    }

    public function restore(int $id): ?GarmentCondition
    {
        $condition = GarmentCondition::onlyTrashed()->find($id);
        if ($condition) {
            $condition->restore();
        }
        return $condition;
    }
}
