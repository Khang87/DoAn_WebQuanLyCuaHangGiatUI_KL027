<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\SettledOrderException;
use App\Models\DonHang;
use App\Models\HoaDon;
use App\Models\ThanhToan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private OrderService $orderService,
    ) {}

    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = ThanhToan::query();

        if (! empty($filters['order_id'])) {
            $query->where('DonHangID', $filters['order_id']);
        }

        if (! empty($filters['invoice_id'])) {
            $query->whereHas('donHang.hoaDons', fn ($invoiceQuery) => $invoiceQuery->where('HoaDonID', $filters['invoice_id']));
        }

        if (! empty($filters['method'])) {
            $method = match ($filters['method']) {
                'cash' => 'Tiền mặt',
                'bank_transfer' => 'Chuyển khoản',
                default => $filters['method'],
            };
            $query->where('PhuongThuc', $method);
        }

        if (! empty($filters['status'])) {
            $query->where('TrangThai', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $numericPart = preg_replace('/[^0-9]/', '', $search);
                if (! empty($numericPart)) {
                    $q->where('ThanhToanID', $numericPart);
                }
                $q->orWhereHas('donHang', function ($sub) use ($search) {
                    $sub->where('MaDonHang', 'LIKE', "%{$search}%")
                        ->orWhereHas('khachHang', function ($cust) use ($search) {
                            $cust->where('HoTen', 'LIKE', "%{$search}%")
                                ->orWhere('SoDienThoai', 'LIKE', "%{$search}%");
                        })
                        ->orWhereHas('hoaDons', function ($inv) use ($search) {
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
            'id_desc' => ['ThanhToanID', 'desc'],
            'id_asc' => ['ThanhToanID', 'asc'],
        ];
        $sort = $filters['sort'] ?? 'latest';
        [$sortBy, $sortOrder] = $sortMap[$sort] ?? ['ThoiGian', 'desc'];

        return $query->with(['donHang.khachHang', 'donHang.hoaDons', 'donHang.thanhToans'])->orderBy($sortBy, $sortOrder)->paginate(10)->withQueryString();
    }

    public function find(int $id): ?ThanhToan
    {
        return ThanhToan::with('donHang.khachHang', 'donHang.hoaDons')->find($id);
    }

    public function create(array $data): ThanhToan
    {
        // Đơn đã quyết toán thì không được ghi thêm khoản thu.
        $invoice = ! empty($data['invoice_id']) ? HoaDon::find($data['invoice_id']) : null;
        $targetOrderId = $data['order_id'] ?? $invoice?->DonHangID;

        if ($invoice && ! empty($data['order_id']) && (int) $data['order_id'] !== (int) $invoice->DonHangID) {
            throw ValidationException::withMessages([
                'invoice_id' => 'Hóa đơn không thuộc đơn hàng đã chọn.',
            ]);
        }

        if (! empty($targetOrderId) && DonHang::find($targetOrderId)?->isLocked()) {
            throw SettledOrderException::forOrder($targetOrderId);
        }

        if (trim((string) ($data['transaction_code'] ?? '')) === '') {
            $order = $targetOrderId ? DonHang::find($targetOrderId) : null;
            $method = $data['method'] ?? $data['PhuongThuc'] ?? '';
            $prefix = in_array($method, ['cash', 'Tiền mặt'], true) ? 'TM' : 'CK';
            $baseCode = $prefix.'_'.($order?->MaDonHang ?: 'TT').'_'.now()->format('YmdHis');
            $transactionCode = $baseCode;
            $suffix = 1;

            while (ThanhToan::query()->where('MaGiaoDich', $transactionCode)->exists()) {
                $transactionCode = $baseCode.'_'.str_pad((string) $suffix++, 2, '0', STR_PAD_LEFT);
            }

            $data['transaction_code'] = $transactionCode;
        }

        return DB::transaction(function () use ($data, $targetOrderId) {
            $data['order_id'] = $targetOrderId;

            $payment = ThanhToan::create($this->mapInput($data));

            $invoice = $payment->donHang?->hoaDons?->first();
            $order = $payment->donHang;

            if ($invoice) {
                // Update invoice status based on total payments
                $this->updateInvoiceStatus($invoice);
            }

            if ($order) {
                $totalPaid = $this->paidTotalFor($order);
                $grandTotal = $order->hoaDons?->first()?->ThanhTien ?? $order->ThanhTien;

                $this->synchronizeOrderPaymentStatus($order, $totalPaid, (float) $grandTotal);
            }

            return $payment->fresh();
        });
    }

    public function update(ThanhToan $payment, array $data, bool $override = false): ThanhToan
    {
        if (isset($data['order_id']) && (int) $data['order_id'] !== (int) $payment->DonHangID) {
            throw ValidationException::withMessages([
                'order_id' => 'Không thể chuyển khoản thanh toán sang đơn hàng khác.',
            ]);
        }

        if (! empty($data['invoice_id'])) {
            $invoice = HoaDon::find($data['invoice_id']);
            if ($invoice && (int) $invoice->DonHangID !== (int) $payment->DonHangID) {
                throw ValidationException::withMessages([
                    'invoice_id' => 'Hóa đơn không thuộc đơn hàng của khoản thanh toán.',
                ]);
            }
        }

        if (! $override) {
            $this->guardSettledPayment($payment);
        }

        $payment->update($this->mapInput($data));

        $invoice = $payment->donHang?->hoaDons?->first();
        if ($invoice) {
            $this->updateInvoiceStatus($invoice);
        }

        $order = $payment->donHang;
        if ($order) {
            $totalPaid = $this->paidTotalFor($order);
            $grandTotal = $order->hoaDons?->first()?->ThanhTien ?? $order->ThanhTien;

            $this->synchronizeOrderPaymentStatus($order, $totalPaid, (float) $grandTotal, $override);
        }

        return $payment->fresh();
    }

    /**
     * Map form/API names to the exact PostgreSQL column names.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mapInput(array $data): array
    {
        $method = $data['PhuongThuc'] ?? $data['method'] ?? null;
        $method = match ($method) {
            'cash' => 'Tiền mặt',
            'bank_transfer' => 'Chuyển khoản',
            default => $method,
        };

        $mapped = [
            'DonHangID' => $data['DonHangID'] ?? $data['order_id'] ?? null,
            'SoTien' => $data['SoTien'] ?? $data['amount'] ?? null,
            'PhuongThuc' => $method,
            'MaGiaoDich' => $data['MaGiaoDich'] ?? $data['transaction_code'] ?? null,
            'ThoiGian' => $data['ThoiGian'] ?? $data['paid_at'] ?? now(),
            'TrangThai' => PaymentStatus::parse($data['TrangThai'] ?? $data['status'] ?? PaymentStatus::Pending->value)->value,
            'GhiChu' => $data['GhiChu'] ?? $data['notes'] ?? null,
        ];

        return array_filter($mapped, static fn (mixed $value): bool => $value !== null);
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
                $payment->MaGiaoDich ?: ('#'.$payment->ThanhToanID)
            ));
        }

        if ($payment->donHang?->isLocked()) {
            throw SettledOrderException::forOrder($payment->donHang->MaDonHang);
        }
    }

    /**
     * Tổng tiền đã ghi nhận của đơn. Bản ghi đã xoá mềm không phải tiền trong quỹ
     * nên bị loại (quan hệ Order::payments() nạp cả bản ghi đã xoá để hiển thị).
     */
    private function paidTotalFor(DonHang $order): float
    {
        return (float) $order->thanhToans()
            ->where('TrangThai', PaymentStatus::Paid->value)
            ->sum('SoTien');
    }

    private function synchronizeOrderPaymentStatus(
        DonHang $order,
        float $totalPaid,
        float $grandTotal,
        bool $override = false,
    ): void {
        if ($totalPaid >= $grandTotal && $order->statusEnum() === OrderStatus::Delivered) {
            $this->orderService->updateStatus($order, OrderStatus::Paid->value, $override);

            return;
        }

        if ($totalPaid < $grandTotal && $order->statusEnum() === OrderStatus::Paid) {
            $this->orderService->updateStatus($order, OrderStatus::Delivered->value, $override);
        }
    }

    private function updateInvoiceStatus(HoaDon $invoice): void
    {
        $donHang = $invoice->donHang;
        $totalPaid = $donHang ? $donHang->thanhToans()->where('TrangThai', PaymentStatus::Paid->value)->sum('SoTien') : 0;
        $grandTotal = $invoice->ThanhTien;

        if ($totalPaid >= $grandTotal) {
            $invoice->update(['TrangThai' => InvoiceStatus::Paid->value]);
        } else {
            $invoice->update(['TrangThai' => InvoiceStatus::Unpaid->value]);
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
            $grandTotal = $order->hoaDons?->first()?->ThanhTien ?? $order->ThanhTien;

            $this->synchronizeOrderPaymentStatus($order, $totalPaid, (float) $grandTotal, $override);
        }

        return $result;
    }
}
