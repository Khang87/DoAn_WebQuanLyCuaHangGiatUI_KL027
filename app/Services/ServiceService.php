<?php

namespace App\Services;

use App\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ServiceService
{
    public function getAll(array $filters = []): LengthAwarePaginator
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

        $sortMap = [
            'created_at_desc' => ['created_at', 'desc'],
            'created_at_asc' => ['created_at', 'asc'],
            'price_asc' => ['price', 'asc'],
            'price_desc' => ['price', 'desc'],
            'name_asc' => ['name', 'asc'],
            'name_desc' => ['name', 'desc'],
            'status_asc' => ['status', 'asc'],
            'status_desc' => ['status', 'desc'],
        ];

        $sort = $filters['sort'] ?? 'created_at_desc';
        [$sortBy, $sortOrder] = $sortMap[$sort] ?? ['created_at', 'desc'];

        return $query->with('category')->orderBy($sortBy, $sortOrder)->paginate(10);
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
