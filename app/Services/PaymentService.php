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

        return $query->with('order')->withTrashed()->latest()->paginate(20);
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
