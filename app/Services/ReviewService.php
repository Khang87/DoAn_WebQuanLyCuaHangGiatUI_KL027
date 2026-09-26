<?php

namespace App\Services;

use App\Models\Review;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ReviewService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Review::query();

        if (!empty($filters['rating'])) {
            $query->where('rating', $filters['rating']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('customer', fn($c) => $c->where('name', 'LIKE', "%{$search}%"))
                    ->orWhere('content', 'LIKE', "%{$search}%");
            });
        }

        $allowedSorts = ['id', 'rating', 'status', 'reviewed_at', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts) ? $filters['sort_by'] : 'created_at';
        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->with('customer', 'order')
            ->orderBy($sortBy, $sortOrder)
            ->latest('id')
            ->paginate(10);
    }

    /**
     * Điểm đánh giá trung bình, làm tròn 1 chữ số thập phân (vd: 4.8).
     * Chỉ tính các đánh giá đang hiển thị, trả về 0.0 khi chưa có đánh giá.
     */
    public function getAverageRating(): float
    {
        return round((float) (Review::where('status', 'visible')->avg('rating') ?? 0), 1);
    }

    public function getTotalReviews(): int
    {
        return Review::where('status', 'visible')->count();
    }

    public function find(int $id): ?Review
    {
        return Review::withTrashed()->with(['customer', 'order'])->find($id);
    }

    public function create(array $data): Review
    {
        return DB::transaction(function () use ($data) {
            // Check if order exists and is completed
            $order = Order::find($data['order_id']);
            if (!$order) {
                throw new \InvalidArgumentException('Đơn hàng không tồn tại.');
            }

            if ($order->status !== 'completed') {
                throw new \InvalidArgumentException('Chỉ có thể đánh giá đơn hàng đã hoàn thành.');
            }

            // Check if review already exists for this order
            if (Review::where('order_id', $data['order_id'])->exists()) {
                throw new \InvalidArgumentException('Đơn hàng này đã được đánh giá.');
            }

            if (empty($data['reviewed_at'])) {
                $data['reviewed_at'] = now();
            }

            return Review::create($data);
        });
    }

    public function update(Review $review, array $data): Review
    {
        $review->update($data);
        return $review->fresh();
    }

    public function delete(Review $review): bool
    {
        return $review->delete();
    }

    public function toggleStatus(Review $review): Review
    {
        $review->update([
            'status' => $review->status === 'visible' ? 'hidden' : 'visible'
        ]);
        return $review->fresh();
    }
}