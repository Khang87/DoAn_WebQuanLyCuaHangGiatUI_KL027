<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Invoice::query();

        if (!empty($filters['order_id'])) {
            $query->where('order_id', $filters['order_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->with('order')->withTrashed()->latest()->paginate(20);
    }

    public function find(int $id): ?Invoice
    {
        return Invoice::withTrashed()->find($id);
    }

    public function create(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            if (empty($data['code'])) {
                $data['code'] = 'HD' . str_pad((string) (Invoice::max('id') ?? 0) + 1, 3, '0', STR_PAD_LEFT);
            }

            $invoice = Invoice::create($data);

            if (!empty($data['order_id'])) {
                Order::where('id', $data['order_id'])->update(['status' => 'completed']);
            }

            return $invoice->fresh();
        });
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        $invoice->update($data);
        return $invoice->fresh();
    }

    public function delete(Invoice $invoice): bool
    {
        return $invoice->delete();
    }

    public function getTotalRevenue(): float
    {
        return (float) Invoice::where('status', 'paid')->sum('total');
    }
}
