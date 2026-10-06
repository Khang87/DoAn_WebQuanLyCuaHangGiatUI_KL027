<?php

namespace App\Services;

use App\Models\DanhMucLoaiDoGiat;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use RuntimeException;

class GarmentCategoryService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = DanhMucLoaiDoGiat::query()->withCount('loaiDoGiats');

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($categoryQuery) use ($search): void {
                $numericPart = preg_replace('/[^0-9]/', '', $search);
                if ($numericPart !== '') {
                    $categoryQuery->where('DanhMucID', $numericPart);
                }

                $categoryQuery->orWhere('TenDanhMuc', 'LIKE', "%{$search}%")
                    ->orWhere('MoTa', 'LIKE', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('TrangThai', $filters['status']);
        }

        $sortMap = [
            'latest' => ['NgayTao', 'desc'],
            'oldest' => ['NgayTao', 'asc'],
            'name_asc' => ['TenDanhMuc', 'asc'],
            'name_desc' => ['TenDanhMuc', 'desc'],
        ];
        [$sortBy, $sortOrder] = $sortMap[$filters['sort'] ?? 'latest'] ?? $sortMap['latest'];

        return $query
            ->orderBy($sortBy, $sortOrder)
            ->orderBy('DanhMucID', $sortOrder)
            ->paginate(10)
            ->withQueryString();
    }

    public function find(int $id): ?DanhMucLoaiDoGiat
    {
        return DanhMucLoaiDoGiat::query()->find($id);
    }

    public function create(array $data): DanhMucLoaiDoGiat
    {
        return DanhMucLoaiDoGiat::query()->create($data);
    }

    public function update(DanhMucLoaiDoGiat $category, array $data): DanhMucLoaiDoGiat
    {
        $category->update($data);

        return $category->fresh();
    }

    public function delete(DanhMucLoaiDoGiat $category): bool
    {
        if ($category->loaiDoGiats()->exists()) {
            if (! $category->update(['TrangThai' => 'Tạm ngưng'])) {
                throw new RuntimeException('Không thể tạm ngưng danh mục loại đồ giặt.');
            }

            return false;
        }

        return (bool) $category->delete();
    }
}
