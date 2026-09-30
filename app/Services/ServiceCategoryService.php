<?php

namespace App\Services;

use App\Models\LoaiDichVu;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ServiceCategoryService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = LoaiDichVu::query();

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $numericPart = preg_replace('/[^0-9]/', '', $search);
                if (!empty($numericPart)) {
                    $q->where('LoaiDichVuID', $numericPart);
                }
                $q->orWhere('TenLoaiDichVu', 'LIKE', "%{$search}%")
                    ->orWhere('MoTa', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('TrangThai', $filters['status']);
        }

        $sortMap = [
            'created_at_desc' => ['NgayTao', 'desc'],
            'created_at_asc' => ['NgayTao', 'asc'],
            'name_asc' => ['TenLoaiDichVu', 'asc'],
            'name_desc' => ['TenLoaiDichVu', 'desc'],
        ];
        $sort = $filters['sort'] ?? 'latest';
        [$sortBy, $sortOrder] = $sortMap[$sort] ?? ['NgayTao', 'desc'];

        return $query->orderBy($sortBy, $sortOrder)->paginate(10)->withQueryString();
    }

    public function find(int $id): ?LoaiDichVu
    {
        return LoaiDichVu::withTrashed()->find($id);
    }

    public function create(array $data): LoaiDichVu
    {
        if (empty($data['TenLoaiDichVu'])) {
            return null;
        }
        if (empty($data['TrangThai'])) {
            $data['TrangThai'] = 'Hoạt động';
        }
        return LoaiDichVu::create($data);
    }

    public function update(LoaiDichVu $category, array $data): LoaiDichVu
    {
        $category->update($data);
        return $category->fresh();
    }

    public function delete(LoaiDichVu $category): bool
    {
        return $category->delete();
    }

    public function restore(int $id): ?LoaiDichVu
    {
        $category = LoaiDichVu::onlyTrashed()->find($id);
        if ($category) {
            $category->restore();
        }
        return $category;
    }
}
