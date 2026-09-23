<?php

namespace App\Services;

use App\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ServiceService
{
    public function getAll(array $filters = []): Collection
    {
        $query = Service::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%");
        }

        if (!empty($filters['category_id'])) {
            $query->where('service_category_id', $filters['category_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->with('category')->paginate(20);
    }

    public function find(int $id): ?Service
    {
        return Service::withTrashed()->find($id);
    }

    public function create(array $data): Service
    {
        if (empty($data['status'])) {
            $data['status'] = 'active';
        }
        return Service::create($data);
    }

    public function update(Service $service, array $data): Service
    {
        $service->update($data);
        return $service->fresh();
    }

    public function delete(Service $service): bool
    {
        return $service->delete();
    }

    public function restore(int $id): ?Service
    {
        $service = Service::onlyTrashed()->find($id);
        if ($service) {
            $service->restore();
        }
        return $service;
    }
}
