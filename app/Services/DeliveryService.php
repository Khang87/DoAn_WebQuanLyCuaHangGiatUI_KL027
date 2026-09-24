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
                $q->orWhere('address', 'LIKE', "%{$search}%")
                    ->orWhere('notes', 'LIKE', "%{$search}%")
                    ->orWhereHas('customer', function ($sub) use ($search) {
                        $sub->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('phone', 'LIKE', "%{$search}%");
                    });
            });
        }

        return $query->with('customer')->withTrashed()->latest()->paginate(10)->withQueryString();
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
