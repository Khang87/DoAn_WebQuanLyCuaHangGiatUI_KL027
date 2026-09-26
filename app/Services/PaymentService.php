<?php

namespace App\Services;

use App\Exceptions\SettledOrderException;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Payment::query();

        if (! empty($filters['order_id'])) {
            $query->where('order_id', $filters['order_id']);
        }

        if (! empty($filters['invoice_id'])) {
            $query->where('invoice_id', $filters['invoice_id']);
        }

        if (! empty($filters['method'])) {
            $query->where('method', $filters['method']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $numericPart = preg_replace('/[^0-9]/', '', $search);
                if (! empty($numericPart)) {
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
                $q->orWhere('transaction_code', 'LIKE', "%{$search}%");
            });
        }

        $sortMap = [
            'latest' => ['created_at', 'desc'],
            'oldest' => ['created_at', 'asc'],
            'amount_desc' => ['amount', 'desc'],
            'amount_asc' => ['amount', 'asc'],
            'id_desc' => ['id', 'desc'],
            'id_asc' => ['id', 'asc'],
        ];
        $sort = $filters['sort'] ?? 'latest';
        [$sortBy, $sortOrder] = $sortMap[$sort] ?? ['created_at', 'desc'];

        return $query->with(['order.customer', 'order.invoice', 'order.payments', 'invoice'])->orderBy($sortBy, $sortOrder)->paginate(10)->withQueryString();
    }

    public function find(int $id): ?Payment
    {
        return Payment::withTrashed()->with('order.customer', 'invoice')->find($id);
    }

    public function create(array $data): Payment
    {
        // Đơn đã quyết toán thì không được ghi thêm khoản thu: bước cập nhật
        // trạng thái bên dưới có thể hạ 'completed' về 'processing' và mở khoá đơn.
        $targetOrderId = $data['order_id'] ?? null;
        if (empty($targetOrderId) && ! empty($data['invoice_id'])) {
            $targetOrderId = Invoice::find($data['invoice_id'])?->order_id;
        }

        if (! empty($targetOrderId) && Order::find($targetOrderId)?->isLocked()) {
            throw SettledOrderException::forOrder($targetOrderId);
        }

        return DB::transaction(function () use ($data) {
            // If invoice_id is provided, get order_id from invoice
            if (! empty($data['invoice_id']) && empty($data['order_id'])) {
                $invoice = Invoice::find($data['invoice_id']);
                if ($invoice) {
                    $data['order_id'] = $invoice->order_id;
                }
            }

            if (empty($data['paid_at'])) {
                $data['paid_at'] = now();
            }

            $payment = Payment::create($data);

            $invoice = $payment->invoice ?? ($payment->order?->invoice);
            $order = $payment->order;

            if ($invoice) {
                // Update invoice status based on total payments
                $this->updateInvoiceStatus($invoice);
            }

            if ($order) {
                // Update order status based on payment
                $totalPaid = $this->paidTotalFor($order);
                $grandTotal = $order->invoice?->grand_total ?? $order->total_amount;

                if ($totalPaid >= $grandTotal) {
                    $order->update(['status' => 'completed']);
                } elseif ($totalPaid > 0) {
                    $order->update(['status' => 'processing']);
                }
            }

            return $payment->fresh();
        });
    }

    public function update(Payment $payment, array $data, bool $override = false): Payment
    {
        if (! $override) {
            $this->guardSettledPayment($payment);
        }

        $payment->update($data);

        $invoice = $payment->invoice ?? ($payment->order?->invoice);
        if ($invoice) {
            $this->updateInvoiceStatus($invoice);
        }

        $order = $payment->order;
        if ($order) {
            $totalPaid = $this->paidTotalFor($order);
            $grandTotal = $order->invoice?->grand_total ?? $order->total_amount;

            if ($totalPaid >= $grandTotal) {
                $order->update(['status' => 'completed']);
            } elseif ($totalPaid > 0) {
                $order->update(['status' => 'processing']);
            }
        }

        return $payment->fresh();
    }

    /**
     * Khoản thu đã ghi nhận tiền thật, hoặc đã gắn với hóa đơn/đơn đã quyết
     * toán, thì không được sửa/xoá vì sẽ làm lệch số tiền đã thu.
     */
    private function guardSettledPayment(Payment $payment): void
    {
        $invoice = $payment->invoice ?? $payment->order?->invoice;

        if ($invoice?->isPaid()) {
            throw SettledOrderException::forInvoice($invoice->code);
        }

        if ($payment->isSettled()) {
            throw new SettledOrderException(sprintf(
                'Khoản thu %s đã ghi nhận tiền nên chỉ có thể xem, không thể chỉnh sửa hoặc xóa.',
                $payment->transaction_code ?: ('#' . $payment->id)
            ));
        }

        if ($payment->order?->isLocked()) {
            throw SettledOrderException::forOrder($payment->order->code);
        }
    }

    /**
     * Tổng tiền đã ghi nhận của đơn. Bản ghi đã xoá mềm không phải tiền trong quỹ
     * nên bị loại (quan hệ Order::payments() nạp cả bản ghi đã xoá để hiển thị).
     */
    private function paidTotalFor(Order $order): float
    {
        return (float) $order->payments()
            ->whereNull('deleted_at')
            ->where('status', 'paid')
            ->sum('amount');
    }

    private function updateInvoiceStatus(Invoice $invoice): void
    {
        $totalPaid = $invoice->payments()->where('status', 'paid')->sum('amount');
        $grandTotal = $invoice->grand_total;

        if ($totalPaid >= $grandTotal) {
            $invoice->update(['status' => 'paid']);
        } elseif ($totalPaid > 0) {
            $invoice->update(['status' => 'partial']);
        } else {
            $invoice->update(['status' => 'unpaid']);
        }
    }

    public function delete(Payment $payment, bool $override = false): bool
    {
        if (! $override) {
            $this->guardSettledPayment($payment);
        }

        $invoice = $payment->invoice ?? ($payment->order?->invoice);
        $order = $payment->order;

        $result = $payment->delete();

        if ($invoice) {
            $this->updateInvoiceStatus($invoice);
        }

        if ($order) {
            $totalPaid = $this->paidTotalFor($order);
            $grandTotal = $order->invoice?->grand_total ?? $order->total_amount;

            if ($totalPaid >= $grandTotal) {
                $order->update(['status' => 'completed']);
            } elseif ($totalPaid > 0) {
                $order->update(['status' => 'processing']);
            } else {
                $order->update(['status' => 'pending']);
            }
        }

        return $result;
    }
}
