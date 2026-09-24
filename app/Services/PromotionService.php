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

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->withTrashed()->latest()->paginate(20);
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
