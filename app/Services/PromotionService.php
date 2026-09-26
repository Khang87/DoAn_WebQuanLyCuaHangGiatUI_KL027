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
                    ->orWhere('discount_type', 'LIKE', "%{$search}%")
                    ->orWhere('discount_value', 'LIKE', "%{$search}%");
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
            'code_asc' => ['code', 'asc'],
            'code_desc' => ['code', 'desc'],
            'discount_value_asc' => ['discount_value', 'asc'],
            'discount_value_desc' => ['discount_value', 'desc'],
            'expires_at_asc' => ['expires_at', 'asc'],
            'expires_at_desc' => ['expires_at', 'desc'],
        ];
        $sort = $filters['sort'] ?? 'latest';
        [$sortBy, $sortOrder] = $sortMap[$sort] ?? ['created_at', 'desc'];

        return $query->orderBy($sortBy, $sortOrder)->paginate(10)->withQueryString();
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
                $query->whereNull('starts_at')->orWhereDate('starts_at', '<=', today());
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', today());
            })
            ->get();
    }
}