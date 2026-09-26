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

        $allowedSorts = ['id', 'amount', 'method', 'status', 'paid_at', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts) ? $filters['sort_by'] : 'created_at';
        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->with('order.customer', 'order.invoice', 'invoice')->withTrashed()->orderBy($sortBy, $sortOrder)->paginate(10)->withQueryString();
    }

    public function find(int $id): ?Payment
    {
        return Payment::withTrashed()->with('order.customer', 'invoice')->find($id);
    }

    public function create(array $data): Payment
    {
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
                $totalPaid = $order->payments()->where('status', 'paid')->sum('amount');
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

    public function update(Payment $payment, array $data): Payment
    {
        $this->guardSettledPayment($payment);

        $payment->update($data);

        $invoice = $payment->invoice ?? ($payment->order?->invoice);
        if ($invoice) {
            $this->updateInvoiceStatus($invoice);
        }

        $order = $payment->order;
        if ($order) {
            $totalPaid = $order->payments()->where('status', 'paid')->sum('amount');
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
     * Khoản thu đã gắn với hóa đơn đã thanh toán thì không được sửa/xoá vì sẽ
     * làm lệch số tiền đã quyết toán.
     */
    private function guardSettledPayment(Payment $payment): void
    {
        $invoice = $payment->invoice ?? $payment->order?->invoice;

        if ($invoice?->isPaid()) {
            throw SettledOrderException::forInvoice($invoice->code);
        }
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

    public function delete(Payment $payment): bool
    {
        $this->guardSettledPayment($payment);

        $invoice = $payment->invoice ?? ($payment->order?->invoice);
        $order = $payment->order;

        $result = $payment->delete();

        if ($invoice) {
            $this->updateInvoiceStatus($invoice);
        }

        if ($order) {
            $totalPaid = $order->payments()->where('status', 'paid')->sum('amount');
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
