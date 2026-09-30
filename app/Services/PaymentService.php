<?php

namespace App\Services;

use App\Exceptions\SettledOrderException;
use App\Models\HoaDon;
use App\Models\DonHang;
use App\Models\ThanhToan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = ThanhToan::query();

        if (! empty($filters['order_id'])) {
            $query->where('DonHangID', $filters['order_id']);
        }

        if (! empty($filters['invoice_id'])) {
            $query->where('DonHangID', $filters['invoice_id']);
        }

        if (! empty($filters['method'])) {
            $query->where('PhuongThuc', $filters['method']);
        }

        if (! empty($filters['status'])) {
            $query->where('TrangThai', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $numericPart = preg_replace('/[^0-9]/', '', $search);
                if (! empty($numericPart)) {
                    $q->where('id', $numericPart);
                }
                $q->orWhereHas('donHang', function ($sub) use ($search) {
                    $sub->where('MaDonHang', 'LIKE', "%{$search}%")
                        ->orWhereHas('customer', function ($cust) use ($search) {
                            $cust->where('HoTen', 'LIKE', "%{$search}%")
                                ->orWhere('SoDienThoai', 'LIKE', "%{$search}%");
                        })
                        ->orWhereHas('invoice', function ($inv) use ($search) {
                            $inv->where('MaHoaDon', 'LIKE', "%{$search}%");
                        });
                });
                $q->orWhere('PhuongThuc', 'LIKE', "%{$search}%");
                $q->orWhere('MaGiaoDich', 'LIKE', "%{$search}%");
            });
        }

        $sortMap = [
            'latest' => ['ThoiGian', 'desc'],
            'oldest' => ['ThoiGian', 'asc'],
            'amount_desc' => ['SoTien', 'desc'],
            'amount_asc' => ['SoTien', 'asc'],
            'id_desc' => ['id', 'desc'],
            'id_asc' => ['id', 'asc'],
        ];
        $sort = $filters['sort'] ?? 'latest';
        [$sortBy, $sortOrder] = $sortMap[$sort] ?? ['ThoiGian', 'desc'];

        return $query->with(['donHang.customer', 'donHang.hoaDons', 'donHang.thanhToans'])->orderBy($sortBy, $sortOrder)->paginate(10)->withQueryString();
    }

    public function find(int $id): ?ThanhToan
    {
        return ThanhToan::withTrashed()->with('donHang.khachHang', 'donHang.hoaDons')->find($id);
    }

    public function create(array $data): ThanhToan
    {
        // Đơn đã quyết toán thì không được ghi thêm khoản thu: bước cập nhật
        // trạng thái bên dưới có thể hạ 'completed' về 'processing' và mở khoá đơn.
        $targetOrderId = $data['order_id'] ?? null;
        if (empty($targetOrderId) && ! empty($data['invoice_id'])) {
            $targetOrderId = HoaDon::find($data['invoice_id'])?->DonHangID;
        }

        if (! empty($targetOrderId) && DonHang::find($targetOrderId)?->isLocked()) {
            throw SettledOrderException::forOrder($targetOrderId);
        }

        return DB::transaction(function () use ($data) {
            // If invoice_id is provided, get order_id from invoice
            if (! empty($data['invoice_id']) && empty($data['order_id'])) {
                $invoice = HoaDon::find($data['invoice_id']);
                if ($invoice) {
                    $data['order_id'] = $invoice->DonHangID;
                }
            }

            $data['ThoiGian'] = $data['paid_at'] ?? now();

            $payment = ThanhToan::create($data);

            $invoice = $payment->donHang?->hoaDons?->first();
            $order = $payment->donHang;

            if ($invoice) {
                // Update invoice status based on total payments
                $this->updateInvoiceStatus($invoice);
            }

            if ($order) {
                // Update order status based on payment
                $totalPaid = $this->paidTotalFor($order);
                $grandTotal = $order->hoaDons?->first()?->ThanhTien ?? $order->total_amount;

                if ($totalPaid >= $grandTotal) {
                    $order->update(['TrangThai' => 'completed']);
                } elseif ($totalPaid > 0) {
                    $order->update(['TrangThai' => 'processing']);
                }
            }

            return $payment->fresh();
        });
    }

    public function update(ThanhToan $payment, array $data, bool $override = false): ThanhToan
    {
        if (! $override) {
            $this->guardSettledPayment($payment);
        }

        $payment->update($data);

        $invoice = $payment->donHang?->hoaDons?->first();
        if ($invoice) {
            $this->updateInvoiceStatus($invoice);
        }

        $order = $payment->donHang;
        if ($order) {
            $totalPaid = $this->paidTotalFor($order);
            $grandTotal = $order->hoaDons?->first()?->ThanhTien ?? $order->total_amount;

            if ($totalPaid >= $grandTotal) {
                $order->update(['TrangThai' => 'completed']);
            } elseif ($totalPaid > 0) {
                $order->update(['TrangThai' => 'processing']);
            }
        }

        return $payment->fresh();
    }

    /**
     * Khoản thu đã ghi nhận tiền thật, hoặc đã gắn với hóa đơn/đơn đã quyết
     * toán, thì không được sửa/xoá vì sẽ làm lệch số tiền đã thu.
     */
    private function guardSettledPayment(ThanhToan $payment): void
    {
        $invoice = $payment->donHang?->hoaDons?->first();

        if ($invoice?->isPaid()) {
            throw SettledOrderException::forInvoice($invoice->MaHoaDon);
        }

        if ($payment->isSettled()) {
            throw new SettledOrderException(sprintf(
                'Khoản thu %s đã ghi nhận tiền nên chỉ có thể xem, không thể chỉnh sửa hoặc xóa.',
                $payment->MaGiaoDich ?: ('#' . $payment->id)
            ));
        }

        if ($payment->donHang?->isLocked()) {
            throw SettledOrderException::forOrder($payment->donHang->code);
        }
    }

    /**
     * Tổng tiền đã ghi nhận của đơn. Bản ghi đã xoá mềm không phải tiền trong quỹ
     * nên bị loại (quan hệ Order::payments() nạp cả bản ghi đã xoá để hiển thị).
     */
    private function paidTotalFor(DonHang $order): float
    {
        return (float) $order->thanhToans()
            ->where('TrangThai', 'paid')
            ->sum('SoTien');
    }

    private function updateInvoiceStatus(HoaDon $invoice): void
    {
        $donHang = $invoice->donHang;
        $totalPaid = $donHang ? $donHang->thanhToans()->where('TrangThai', 'paid')->sum('SoTien') : 0;
        $grandTotal = $invoice->ThanhTien;

        if ($totalPaid >= $grandTotal) {
            $invoice->update(['TrangThai' => 'paid']);
        } elseif ($totalPaid > 0) {
            $invoice->update(['TrangThai' => 'partial']);
        } else {
            $invoice->update(['TrangThai' => 'unpaid']);
        }
    }

    public function delete(ThanhToan $payment, bool $override = false): bool
    {
        if (! $override) {
            $this->guardSettledPayment($payment);
        }

        $invoice = $payment->donHang?->hoaDons?->first();
        $order = $payment->donHang;

        $result = $payment->delete();

        if ($invoice) {
            $this->updateInvoiceStatus($invoice);
        }

        if ($order) {
            $totalPaid = $this->paidTotalFor($order);
            $grandTotal = $order->hoaDons?->first()?->ThanhTien ?? $order->total_amount;

            if ($totalPaid >= $grandTotal) {
                $order->update(['TrangThai' => 'completed']);
            } elseif ($totalPaid > 0) {
                $order->update(['TrangThai' => 'processing']);
            } else {
                $order->update(['TrangThai' => 'pending']);
            }
        }

        return $result;
    }
}
