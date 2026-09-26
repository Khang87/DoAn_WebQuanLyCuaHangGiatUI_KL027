<?php

namespace App\Services;

use App\Models\Pricing;
use App\Models\Service;
use App\Models\Garment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PricingService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Pricing::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhereHas('service', fn($s) => $s->where('name', 'LIKE', "%{$search}%"))
                  ->orWhereHas('garment', fn($g) => $g->where('name', 'LIKE', "%{$search}%"));
            });
        }

        if (!empty($filters['service_id'])) {
            $query->where('service_id', $filters['service_id']);
        }

        if (!empty($filters['garment_id'])) {
            $query->where('garment_id', $filters['garment_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $allowedSorts = ['id', 'name', 'unit', 'price', 'effective_date', 'status', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts) ? $filters['sort_by'] : 'created_at';
        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->with(['service', 'garment'])->withTrashed()->orderBy($sortBy, $sortOrder)->paginate(20);
    }

    public function find(int $id): ?Pricing
    {
        return Pricing::withTrashed()->with(['service', 'garment'])->find($id);
    }

    public function create(array $data): Pricing
    {
        return DB::transaction(function () use ($data) {
            return Pricing::create($data);
        });
    }

    public function update(Pricing $pricing, array $data): Pricing
    {
        $pricing->update($data);
        return $pricing->fresh();
    }

    public function delete(Pricing $pricing): bool
    {
        return $pricing->delete();
    }

    public function restore(int $id): ?Pricing
    {
        $pricing = Pricing::onlyTrashed()->find($id);
        if ($pricing) {
            $pricing->restore();
        }
        return $pricing;
    }

    public function getLatestPrice(int $serviceId, int $garmentId): ?float
    {
        return Pricing::getLatestPrice($serviceId, $garmentId);
    }
}