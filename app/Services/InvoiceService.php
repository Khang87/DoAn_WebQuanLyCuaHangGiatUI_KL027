<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Exceptions\SettledOrderException;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Invoice::query();

        if (! empty($filters['order_id'])) {
            $query->where('order_id', $filters['order_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('invoice_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('invoice_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('code', 'LIKE', '%'.$filters['search'].'%')
                    ->orWhere('notes', 'LIKE', '%'.$filters['search'].'%')
                    ->orWhereHas('order.customer', fn ($sub) => $sub->where('name', 'LIKE', '%'.$filters['search'].'%'));
            });
        }

        return $query->with('order.customer')->withTrashed()->latest()->paginate(20);
    }

    public function find(int|string $id): ?Invoice
    {
        return Invoice::withTrashed()
            ->where('id', $id)
            ->orWhere('code', $id)
            ->first();
    }

    public function findDetailed(int|string $id): ?Invoice
    {
        return Invoice::withTrashed()
            ->where('id', $id)
            ->orWhere('code', $id)
            ->first()
            ?->load(['order.customer', 'order.items.service', 'order.items.garment', 'payments']);
    }

    public function create(array $data): Invoice
    {
        if (! empty($data['order_id']) && Order::find($data['order_id'])?->hasPaidInvoice()) {
            throw SettledOrderException::forOrder($data['order_id']);
        }

        return DB::transaction(function () use ($data) {
            if (empty($data['code'])) {
                $data['code'] = 'HD'.str_pad((string) (Invoice::max('id') ?? 0) + 1, 3, '0', STR_PAD_LEFT);
            }

            if (empty($data['invoice_date'])) {
                $data['invoice_date'] = now()->toDateString();
            }

            $data = $this->normalizeAmounts($data);

            $invoice = Invoice::create($data);

            if (! empty($data['order_id'])) {
                $order = Order::find($data['order_id']);
                if ($order) {
                    $order->update(['status' => 'completed']);
                }
            }

            return $invoice->fresh();
        });
    }

    public function createFromOrder(Order $order): Invoice
    {
        return DB::transaction(function () use ($order) {
            $existingInvoice = $order->invoice;
            if ($existingInvoice) {
                return $existingInvoice->fresh();
            }

            $invoice = Invoice::create([
                'order_id' => $order->id,
                'code' => 'HD'.str_pad((string) (Invoice::max('id') ?? 0) + 1, 3, '0', STR_PAD_LEFT),
                'invoice_date' => now()->toDateString(),
                'total_amount' => $order->total_amount,
                'discount_amount' => $order->discount_by_promotion + $order->discount_by_points,
                'delivery_fee' => 0,
                'grand_total' => $order->total_amount,
                'total' => $order->total_amount,
                'status' => 'unpaid',
                'notes' => $order->notes,
            ]);

            return $invoice->fresh();
        });
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        $this->guardSettledInvoice($invoice, $data);

        $data = $this->normalizeAmounts($data, $invoice);

        $invoice->update($data);

        return $invoice->fresh();
    }

    /**
     * Hóa đơn đã thanh toán là chốt số tiền: chỉ cho sửa ghi chú, mọi thay đổi
     * về khoản tiền hoặc đánh dấu lại là chưa thanh toán đều bị từ chối.
     *
     * @param  array<string, mixed>  $data
     */
    private function guardSettledInvoice(Invoice $invoice, array $data): void
    {
        if (! $invoice->isPaid()) {
            return;
        }

        if (array_key_exists('status', $data)
            && $data['status'] !== null
            && InvoiceStatus::parse($data['status']) !== InvoiceStatus::Paid) {
            throw SettledOrderException::forInvoice($invoice->code);
        }

        foreach (['total', 'total_amount', 'discount_amount', 'delivery_fee', 'grand_total'] as $key) {
            if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
                continue;
            }

            if ((float) $data[$key] !== (float) $invoice->{$key}) {
                throw SettledOrderException::forInvoice($invoice->code);
            }
        }
    }

    public function updateStatus(Invoice $invoice, string $status): Invoice
    {
        if (! in_array($status, InvoiceStatus::values(), true)) {
            throw new \InvalidArgumentException('Trạng thái không hợp lệ');
        }

        // Đã thanh toán thì không được đánh dấu lại là chưa/chưa đủ thanh toán.
        if ($invoice->isPaid() && InvoiceStatus::parse($status) !== InvoiceStatus::Paid) {
            throw SettledOrderException::forInvoice($invoice->code);
        }

        $invoice->update(['status' => $status]);

        return $invoice->fresh();
    }

    /**
     * Chuẩn hóa bộ số tiền của hóa đơn.
     *
     * Form đăng tải dùng `total` (số tiền phải trả) trong khi các cột
     * total_amount / discount_amount / delivery_fee / grand_total phục vụ báo cáo.
     * Hai nhóm này được đồng bộ qua đây để không lệch nhau.
     */
    private function normalizeAmounts(array $data, ?Invoice $invoice = null): array
    {
        $totalAmount = $data['total_amount'] ?? $invoice?->total_amount;
        $discount = $data['discount_amount'] ?? $invoice?->discount_amount ?? 0;
        $deliveryFee = $data['delivery_fee'] ?? $invoice?->delivery_fee ?? 0;

        $total = array_key_exists('total', $data) && $data['total'] !== null && $data['total'] !== ''
            ? (float) $data['total']
            : (float) ($totalAmount ?? 0) - (float) $discount + (float) $deliveryFee;

        $data['total'] = max(0, round($total, 2));
        $data['grand_total'] = $data['total'];

        if ($totalAmount === null) {
            $data['total_amount'] = $data['total'];
        } else {
            $data['total_amount'] = (float) $totalAmount;
        }

        $data['discount_amount'] = (float) $discount;
        $data['delivery_fee'] = (float) $deliveryFee;

        return $data;
    }

    public function delete(Invoice $invoice): bool
    {
        if ($invoice->isPaid()) {
            throw SettledOrderException::forInvoice($invoice->code);
        }

        return $invoice->delete();
    }

    public function getTotalRevenue(): float
    {
        return (float) Invoice::whereIn('status', InvoiceStatus::paidValues())->sum('grand_total');
    }
}
