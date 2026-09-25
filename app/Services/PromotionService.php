<?php

namespace App\Services;

use App\Models\Promotion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PromotionService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Promotion::query();

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $numericPart = preg_replace('/[^0-9]/', '', $search);
                if (!empty($numericPart)) {
                    $q->where('id', $numericPart);
                }
                $q->orWhere('code', 'LIKE', "%{$search}%")
                    ->orWhere('name', 'LIKE', "%{$search}%")
                    ->orWhere('discount', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $allowedSorts = ['id', 'name', 'code', 'discount', 'expires_at', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts) ? $filters['sort_by'] : 'created_at';
        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->withTrashed()->orderBy($sortBy, $sortOrder)->paginate(10)->withQueryString();
    }

    public function find(int $id): ?Promotion
    {
        return Promotion::withTrashed()->find($id);
    }

    public function create(array $data): Promotion
    {
        return Promotion::create($data);
    }

    public function update(Promotion $promotion, array $data): Promotion
    {
        $promotion->update($data);
        return $promotion->fresh();
    }

    public function delete(Promotion $promotion): bool
    {
        return $promotion->delete();
    }

    public function restore(int $id): ?Promotion
    {
        $promotion = Promotion::onlyTrashed()->find($id);
        if ($promotion) {
            $promotion->restore();
        }
        return $promotion;
    }

    public function getActive(): Collection
    {
        return Promotion::where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', today());
            })
            ->get();
    }
}
