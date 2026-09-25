<?php

namespace App\Services;

use App\Models\Delivery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DeliveryService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Delivery::query();

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (!empty($filters['method'])) {
            $query->where('method', $filters['method']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $numericPart = preg_replace('/[^0-9]/', '', $search);
                if (!empty($numericPart)) {
                    $q->where('id', $numericPart);
                }
                $q->orWhere('code', 'LIKE', "%{$search}%")
                    ->orWhere('address', 'LIKE', "%{$search}%")
                    ->orWhere('notes', 'LIKE', "%{$search}%")
                    ->orWhereHas('customer', function ($sub) use ($search) {
                        $sub->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('phone', 'LIKE', "%{$search}%");
                    });
            });
        }

        $allowedSorts = ['id', 'customer_id', 'method', 'status', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts) ? $filters['sort_by'] : 'created_at';
        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->with('customer')->withTrashed()->orderBy($sortBy, $sortOrder)->paginate(10)->withQueryString();
    }

    public function find(int $id): ?Delivery
    {
        return Delivery::withTrashed()->find($id);
    }

    public function create(array $data): Delivery
    {
        return Delivery::create($data);
    }

    public function update(Delivery $delivery, array $data): Delivery
    {
        $delivery->update($data);
        return $delivery->fresh();
    }

    public function delete(Delivery $delivery): bool
    {
        return $delivery->delete();
    }

    public function restore(int $id): ?Delivery
    {
        $delivery = Delivery::onlyTrashed()->find($id);
        if ($delivery) {
            $delivery->restore();
        }
        return $delivery;
    }
}
