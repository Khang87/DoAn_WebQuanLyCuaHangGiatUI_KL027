<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Order::query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where('code', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $allowedSorts = ['id', 'code', 'total_amount', 'status', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts) ? $filters['sort_by'] : 'created_at';
        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->with(['customer', 'service', 'items'])->withTrashed()->orderBy($sortBy, $sortOrder)->paginate(20);
    }

    public function find(int $id): ?Order
    {
        return Order::with(['customer', 'service', 'items'])->withTrashed()->find($id);
    }

    public function create(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            if (empty($data['code'])) {
                $data['code'] = 'DH' . str_pad((string) ((Order::max('id') ?? 0) + 1), 3, '0', STR_PAD_LEFT);
            }
            $order = Order::create($data);

            if (!empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $item['subtotal'] = $item['price'] * $item['quantity'];
                    OrderItem::create(array_merge(['order_id' => $order->id], $item));
                }
            }

            return $order->fresh();
        });
    }

    public function update(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data) {
            $order->update($data);

            if (!empty($data['items'])) {
                $order->items()->delete();
                foreach ($data['items'] as $item) {
                    $item['subtotal'] = $item['price'] * $item['quantity'];
                    OrderItem::create(array_merge(['order_id' => $order->id], $item));
                }
            }

            return $order->fresh();
        });
    }

    public function delete(Order $order): bool
    {
        return DB::transaction(function () use ($order) {
            return $order->delete();
        });
    }

    public function restore(int $id): ?Order
    {
        $order = Order::onlyTrashed()->find($id);
        if ($order) {
            $order->restore();
        }
        return $order;
    }

    public function getStatusFlow(): array
    {
        return [
            'pending' => 'Chờ xác nhận',
            'processing' => 'Đã nhận đồ',
            'washing' => 'Đang giặt',
            'washed' => 'Đã giặt xong',
            'delivering' => 'Đang giao',
            'completed' => 'Hoàn thành',
            'cancelled' => 'Đã hủy',
        ];
    }
}
