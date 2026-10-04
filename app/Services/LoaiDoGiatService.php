<?php

namespace App\Services;

use App\Models\LoaiDoGiat;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LoaiDoGiatService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = LoaiDoGiat::query();

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($query) use ($search): void {
                $query->where('TenLoaiDoGiat', 'like', "%{$search}%")
                    ->orWhere('MoTa', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('TrangThai', $filters['status']);
        }

        $allowedSorts = ['LoaiDoGiatID', 'TenLoaiDoGiat', 'TrangThai'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts) ? $filters['sort_by'] : 'LoaiDoGiatID';
        $sortOrder = ($filters['sort_order'] ?? 'asc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortOrder)->paginate(10)->withQueryString();
    }

    public function find(int $id): ?LoaiDoGiat
    {
        return LoaiDoGiat::query()->find($id);
    }

    public function create(array $data): LoaiDoGiat
    {
        if (empty($data['TrangThai'])) {
            $data['TrangThai'] = 'Hoạt động';
        }

        return LoaiDoGiat::create($data);
    }

    public function update(LoaiDoGiat $category, array $data): LoaiDoGiat
    {
        $category->update($data);

        return $category->fresh();
    }

    public function delete(LoaiDoGiat $category): ?string
    {
        $relatedRecords = array_filter([
            'bảng giá' => $category->bangGias()->count(),
            'chi tiết đơn hàng' => $category->chiTietDonHangs()->count(),
            'chi tiết lịch hẹn' => $category->chiTietBookings()->count(),
        ]);

        if ($relatedRecords !== []) {
            $category->update(['TrangThai' => 'Tạm ngưng']);

            $details = [];

            foreach ($relatedRecords as $recordType => $count) {
                $details[] = $count.' '.$recordType;
            }

            return 'Không thể xóa loại đồ giặt vì còn dữ liệu liên quan: '.implode(', ', $details).'. Loại đồ đã được tạm ngưng.';
        }

        return $category->delete() ? null : 'Không thể xóa loại đồ giặt. Vui lòng thử lại.';
    }
}
