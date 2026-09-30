<?php

namespace App\Services;

use App\Models\LoaiDichVu;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ServiceCategoryService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = LoaiDichVu::query();

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $numericPart = preg_replace('/[^0-9]/', '', $search);
                if (! empty($numericPart)) {
                    $q->where('LoaiDichVuID', $numericPart);
                }
                $q->orWhere('TenLoaiDichVu', 'LIKE', "%{$search}%")
                    ->orWhere('MoTa', 'LIKE', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('TrangThai', $filters['status']);
        }

        $sortMap = [
            'latest' => ['LoaiDichVuID', 'desc'],
            'oldest' => ['LoaiDichVuID', 'asc'],
            'name_asc' => ['TenLoaiDichVu', 'asc'],
            'name_desc' => ['TenLoaiDichVu', 'desc'],
        ];
        $sort = $filters['sort'] ?? 'latest';
        [$sortBy, $sortOrder] = $sortMap[$sort] ?? ['LoaiDichVuID', 'desc'];

        return $query->orderBy($sortBy, $sortOrder)->paginate(10)->withQueryString();
    }

    public function find(int $id): ?LoaiDichVu
    {
        return LoaiDichVu::find($id);
    }

    public function create(array $data): LoaiDichVu
    {
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
        return $category->update(['TrangThai' => 'Tạm ngưng']);
    }

    public function restore(int $id): ?LoaiDichVu
    {
        $category = LoaiDichVu::find($id);
        if ($category) {
            $category->update(['TrangThai' => 'Hoạt động']);
        }

        return $category;
    }
}
