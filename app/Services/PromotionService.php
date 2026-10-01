<?php

namespace App\Services;

use App\Models\KhuyenMai;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PromotionService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = KhuyenMai::query()->withCount(['donHangs as used_count']);

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $numericPart = preg_replace('/[^0-9]/', '', $search);
                if (! empty($numericPart)) {
                    $q->where('KhuyenMaiID', $numericPart);
                }
                $q->orWhere('MaKhuyenMai', 'LIKE', "%{$search}%")
                    ->orWhere('TenKhuyenMai', 'LIKE', "%{$search}%")
                    ->orWhere('LoaiKhuyenMai', 'LIKE', "%{$search}%")
                    ->orWhere('GiaTriGiam', 'LIKE', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('TrangThai', $filters['status']);
        }

        $sortMap = [
            'created_at_desc' => ['NgayBatDau', 'desc'],
            'created_at_asc' => ['NgayBatDau', 'asc'],
            'name_asc' => ['TenKhuyenMai', 'asc'],
            'name_desc' => ['TenKhuyenMai', 'desc'],
            'code_asc' => ['MaKhuyenMai', 'asc'],
            'code_desc' => ['MaKhuyenMai', 'desc'],
            'discount_value_asc' => ['GiaTriGiam', 'asc'],
            'discount_value_desc' => ['GiaTriGiam', 'desc'],
            'expires_at_asc' => ['NgayKetThuc', 'asc'],
            'expires_at_desc' => ['NgayKetThuc', 'desc'],
        ];
        $sort = $filters['sort'] ?? 'latest';
        [$sortBy, $sortOrder] = $sortMap[$sort] ?? ['KhuyenMaiID', 'desc'];

        return $query->orderBy($sortBy, $sortOrder)->paginate(10)->withQueryString();
    }

    public function find(int $id): ?KhuyenMai
    {
        return KhuyenMai::query()
            ->withCount(['donHangs as used_count'])
            ->find($id);
    }

    public function create(array $data): KhuyenMai
    {
        return KhuyenMai::create($data);
    }

    public function update(KhuyenMai $promotion, array $data): KhuyenMai
    {
        $promotion->update($data);

        return $promotion->fresh();
    }

    public function delete(KhuyenMai $promotion): bool
    {
        if ($promotion->donHangs()->exists()) {
            $promotion->update(['TrangThai' => 'Tạm ngưng']);

            return true;
        }

        return $promotion->delete();
    }

    public function getActive(): Collection
    {
        return KhuyenMai::where('TrangThai', 'Hoạt động')
            ->whereDate('NgayBatDau', '<=', today())
            ->whereDate('NgayKetThuc', '>=', today())
            ->get();
    }
}
