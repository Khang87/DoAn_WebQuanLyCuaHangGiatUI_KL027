<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Payment::query();

        if (!empty($filters['order_id'])) {
            $query->where('order_id', $filters['order_id']);
        }

        if (!empty($filters['method'])) {
            $query->where('method', $filters['method']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $numericPart = preg_replace('/[^0-9]/', '', $search);
                if (!empty($numericPart)) {
                    $q->where('id', $numericPart);
                }
                $q->orWhereHas('order', function ($sub) use ($search) {
                    $sub->where('code', 'LIKE', "%{$search}%")
                        ->orWhereHas('customer', function ($cust) use ($search) {
                            $cust->where('name', 'LIKE', "%{$search}%")
                                ->orWhere('phone', 'LIKE', "%{$search}%");
                        })
                        ->orWhereHas('invoice', function ($inv) use ($search) {
                            $inv->where('code', 'LIKE', "%{$search}%");
                        });
                });
                $q->orWhere('method', 'LIKE', "%{$search}%");
            });
        }

        $allowedSorts = ['id', 'amount', 'method', 'status', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts) ? $filters['sort_by'] : 'created_at';
        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->with('order.customer', 'order.invoice')->withTrashed()->orderBy($sortBy, $sortOrder)->paginate(10)->withQueryString();
    }

    public function find(int $id): ?Payment
    {
        return Payment::withTrashed()->find($id);
    }

    public function create(array $data): Payment
    {
        return DB::transaction(function () use ($data) {
            $payment = Payment::create($data);

            $order = $payment->order;
            if ($order) {
                $order->update(['status' => 'processing']);
            }

            return $payment->fresh();
        });
    }

    public function update(Payment $payment, array $data): Payment
    {
        $payment->update($data);
        return $payment->fresh();
    }

    public function delete(Payment $payment): bool
    {
        return $payment->delete();
    }
}
