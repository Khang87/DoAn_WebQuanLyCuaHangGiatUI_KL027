<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
use App\Models\DanhGia;
use App\Models\DonHang;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ReviewService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = DanhGia::query();

        if (! empty($filters['rating'])) {
            $query->where('SoSao', $filters['rating']);
        }

        if (! empty($filters['status'])) {
            $status = ReviewStatus::tryFrom((string) $filters['status']);
            $query->where('TrangThai', $status?->label() ?? $filters['status']);
        }

        if (! empty($filters['customer_id'])) {
            $query->where('KhachHangID', $filters['customer_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('khachHang', fn ($c) => $c->where('HoTen', 'LIKE', "%{$search}%"))
                    ->orWhere('BinhLuan', 'LIKE', "%{$search}%");
            });
        }

        $allowedSorts = ['DanhGiaID', 'SoSao', 'TrangThai', 'NgayDanhGia'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts, true) ? $filters['sort_by'] : 'NgayDanhGia';
        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->with(['khachHang', 'donHang'])
            ->orderBy($sortBy, $sortOrder)
            ->orderByDesc('DanhGiaID')
            ->paginate(10);
    }

    /**
     * Điểm đánh giá trung bình, làm tròn 1 chữ số thập phân (vd: 4.8).
     * Chỉ tính các đánh giá đang hiển thị, trả về 0.0 khi chưa có đánh giá.
     */
    public function getAverageRating(): float
    {
        return round((float) (DanhGia::where('TrangThai', 'Hiển thị')->avg('SoSao') ?? 0), 1);
    }

    public function getTotalReviews(): int
    {
        return DanhGia::where('TrangThai', 'Hiển thị')->count();
    }

    public function find(int $id): ?DanhGia
    {
        return DanhGia::with(['khachHang', 'donHang'])->find($id);
    }

    public function create(array $data): DanhGia
    {
        return DB::transaction(function () use ($data) {
            // Check if order exists and is completed
            $order = DonHang::find($data['order_id']);
            if (! $order) {
                throw new \InvalidArgumentException('Đơn hàng không tồn tại.');
            }

            if ($order->TrangThai !== OrderStatus::Delivered->value) {
                throw new \InvalidArgumentException('Chỉ có thể đánh giá đơn hàng đã hoàn thành.');
            }

            // Check if review already exists for this order
            if (DanhGia::where('DonHangID', $data['order_id'])->exists()) {
                throw new \InvalidArgumentException('Đơn hàng này đã được đánh giá.');
            }

            if (empty($data['reviewed_at'])) {
                $data['reviewed_at'] = now();
            }

            return DanhGia::create($data);
        });
    }

    public function update(DanhGia $review, array $data): DanhGia
    {
        $review->update($data);

        return $review->fresh();
    }

    public function delete(DanhGia $review): bool
    {
        return $review->delete();
    }

    public function toggleStatus(DanhGia $review): DanhGia
    {
        $review->update([
            'TrangThai' => $review->TrangThai === 'Hiển thị' ? 'Ẩn' : 'Hiển thị',
        ]);

        return $review->fresh();
    }
}
