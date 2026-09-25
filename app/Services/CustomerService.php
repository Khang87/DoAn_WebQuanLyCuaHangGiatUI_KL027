<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Customer::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $allowedSorts = ['id', 'name', 'email', 'phone', 'points', 'type', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts) ? $filters['sort_by'] : 'created_at';
        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->withTrashed()->orderBy($sortBy, $sortOrder)->paginate(20);
    }

    public function find(int $id): ?Customer
    {
        return Customer::withTrashed()->find($id);
    }

    public function findByCode(string $code): ?Customer
    {
        return Customer::where('code', $code)->first();
    }

    public function create(array $data): Customer
    {
        if (empty($data['code'])) {
            $data['code'] = 'KH' . str_pad((string) ((Customer::max('id') ?? 0) + 1), 3, '0', STR_PAD_LEFT);
        }
        if (empty($data['points'])) {
            $data['points'] = 0;
        }
        if (empty($data['points'])) {
            $data['points'] = 0;
        }
        unset($data['type']);
        $customer = Customer::create($data);
        return $customer->fresh();
    }

    public function update(Customer $customer, array $data): Customer
    {
        if (isset($data['points'])) {
            $customer->points = $data['points'];
            unset($data['points']);
        }
        $customer->update($data);
        return $customer->fresh();
    }

    public function delete(Customer $customer): bool
    {
        return $customer->delete();
    }

    public function restore(int $id): ?Customer
    {
        $customer = Customer::onlyTrashed()->find($id);
        if ($customer) {
            $customer->restore();
        }
        return $customer;
    }

    public function getTotalSpent(Customer $customer): float
    {
        return (float) $customer->orders()->sum('total_amount');
    }

    public function getOrderCount(Customer $customer): int
    {
        return (int) $customer->orders()->count();
    }
}
