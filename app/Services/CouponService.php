<?php

namespace App\Services;

use App\Models\KhuyenMai;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CouponService
{
    public function __construct(
        private PromotionService $promotionService,
    ) {}

    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = KhuyenMai::query();

        if (! empty($filters['promotion_id'])) {
            $query->where('KhuyenMaiID', $filters['promotion_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('TrangThai', $filters['status']);
        }

        return $query->latest()->paginate(10);
    }

    public function find(int $id): ?KhuyenMai
    {
        return KhuyenMai::find($id);
    }

    public function findByCode(string $code): ?KhuyenMai
    {
        return KhuyenMai::where('MaKhuyenMai', $code)->where('TrangThai', 'Hoạt động')->first();
    }

    public function create(array $data): KhuyenMai
    {
        return $this->promotionService->create($data);
    }

    public function update(KhuyenMai $coupon, array $data): KhuyenMai
    {
        $coupon->update($data);

        return $coupon->fresh();
    }

    public function delete(KhuyenMai $coupon): bool
    {
        return $coupon->delete();
    }
}
