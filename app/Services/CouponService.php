<?php

namespace App\Services;

use App\Models\Coupon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CouponService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Coupon::query();

        if (!empty($filters['promotion_id'])) {
            $query->where('promotion_id', $filters['promotion_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->with('promotion')->withTrashed()->latest()->paginate(20);
    }

    public function find(int $id): ?Coupon
    {
        return Coupon::withTrashed()->find($id);
    }

    public function findByCode(string $code): ?Coupon
    {
        return Coupon::where('code', $code)->where('status', 'active')->first();
    }

    public function create(array $data): Coupon
    {
        return Coupon::create($data);
    }

    public function update(Coupon $coupon, array $data): Coupon
    {
        $coupon->update($data);
        return $coupon->fresh();
    }

    public function delete(Coupon $coupon): bool
    {
        return $coupon->delete();
    }

    public function restore(int $id): ?Coupon
    {
        $coupon = Coupon::onlyTrashed()->find($id);
        if ($coupon) {
            $coupon->restore();
        }
        return $coupon;
    }
}
