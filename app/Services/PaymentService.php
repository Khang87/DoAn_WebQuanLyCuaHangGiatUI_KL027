<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\SettledOrderException;
use App\Models\DonHang;
use App\Models\HoaDon;
use App\Models\NhatKyHeThong;
use App\Models\TaiKhoan;
use App\Models\ThanhToan;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private OrderService $orderService,
    ) {}

    private function filteredPayments(array $filters): Builder
    {
        $query = ThanhToan::query();

        if (! empty($filters['order_id'])) {
            $query->where('DonHangID', $filters['order_id']);
        }

        if (! empty($filters['invoice_id'])) {
            $query->whereHas('donHang.hoaDons', fn ($invoiceQuery) => $invoiceQuery->where('HoaDonID', $filters['invoice_id']));
        }

        if (! empty($filters['date_from'])) {
            $query->where('ThoiGian', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }
        if (! empty($filters['date_to'])) {
            $query->where('ThoiGian', '<', Carbon::parse($filters['date_to'])->addDay()->startOfDay());
        }

        if (! empty($filters['collector_id'])) {
            $query->whereHas('collectionAudit', fn ($audit) => $audit->where('TaiKhoanID', $filters['collector_id']));
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
                if (preg_match('/^(?:TT)?([0-9]+)$/i', $search, $matches)) {
                    $id = ltrim($matches[1], '0') ?: '0';
                    if (strlen($id) < 10 || (strlen($id) === 10 && strcmp($id, '2147483647') <= 0)) {
                        $q->where('ThanhToanID', (int) $id);
                    }
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

        return $query->orderBy($sortBy, $sortOrder)->orderBy('ThanhToanID', $sortOrder);
    }

    public function getAll(array $filters = []): LengthAwarePaginator
    {
        return $this->filteredPayments($filters)
            ->with(['donHang.khachHang.taiKhoan', 'donHang.hoaDons', 'donHang.thanhToans'])
            ->paginate(10)->withQueryString();
    }

    public function historyTotals(array $filters): object
    {
        return $this->filteredPayments($filters)->reorder()->selectRaw(
            'COALESCE(SUM(CASE WHEN "TrangThai" IN (?, ?) THEN "SoTien" ELSE 0 END), 0) AS collected, COALESCE(SUM(CASE WHEN "TrangThai" = ? THEN "SoTien" ELSE 0 END), 0) AS refunded',
            [PaymentStatus::Paid->value, PaymentStatus::Refunded->value, PaymentStatus::Refunded->value]
        )->toBase()->first();
    }

    public function historyCollectors()
    {
        return TaiKhoan::query()->whereIn('TaiKhoanID', NhatKyHeThong::query()->select('TaiKhoanID')
            ->where('BangDuLieu', 'ThanhToan')->where('HanhDong', ThanhToan::COLLECTION_AUDIT_ACTION)->whereNotNull('TaiKhoanID'))
            ->with(['nhanVien', 'khachHang'])->orderBy('TaiKhoanID')->get();
    }

    public function ordersAwaitingCollection(array $filters = []): LengthAwarePaginator
    {
        return DonHang::query()->with(['khachHang.taiKhoan', 'hoaDons' => fn ($invoices) => $invoices->orderBy('HoaDonID')])
            ->whereNotIn('TrangThai', [OrderStatus::Cancelled->value, OrderStatus::Paid->value])
            ->whereRaw('COALESCE((SELECT "ThanhTien" FROM "HoaDon" WHERE "HoaDon"."DonHangID" = "DonHang"."DonHangID" ORDER BY "HoaDonID" LIMIT 1), "DonHang"."ThanhTien") > 0')
            ->whereDoesntHave('hoaDons', fn ($invoices) => $invoices->whereIn('TrangThai', [InvoiceStatus::Paid->value, InvoiceStatus::Cancelled->value]))
            ->whereDoesntHave('thanhToans', fn ($payments) => $payments->where('TrangThai', PaymentStatus::Paid->value))
            ->when(! empty($filters['order_id']), fn ($query) => $query->where('DonHangID', $filters['order_id']))
            ->when(! empty($filters['search']), fn ($query) => $query->where(fn ($search) => $search
                ->where('MaDonHang', 'LIKE', '%'.$filters['search'].'%')
                ->orWhereHas('khachHang', fn ($customers) => $customers->where('HoTen', 'LIKE', '%'.$filters['search'].'%'))))
            ->orderByDesc('NgayTao')->orderByDesc('DonHangID')->paginate(10)->withQueryString();
    }

    public function find(int $id): ?ThanhToan
    {
        return ThanhToan::with('donHang.khachHang.taiKhoan', 'donHang.hoaDons')->find($id);
    }

    public function create(array $data): ThanhToan
    {
        $invoice = ! empty($data['invoice_id']) ? HoaDon::find($data['invoice_id']) : null;
        $targetOrderId = $data['order_id'] ?? $data['DonHangID'] ?? $invoice?->DonHangID;

        if ($invoice && ! empty($data['order_id']) && (int) $data['order_id'] !== (int) $invoice->DonHangID) {
            throw ValidationException::withMessages([
                'invoice_id' => 'Hóa đơn không thuộc đơn hàng đã chọn.',
            ]);
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
            $lockedOrder = DonHang::query()->lockForUpdate()->findOrFail($targetOrderId);
            if ($lockedOrder->statusEnum()->isSettled()) {
                throw SettledOrderException::forOrder($targetOrderId);
            }

            $invoice = $this->lockInvoice($lockedOrder, $data['invoice_id'] ?? null);
            if ($invoice?->isPaid()) {
                throw ValidationException::withMessages([
                    'invoice_id' => 'Hóa đơn đã được thanh toán.',
                ]);
            }
            if ($invoice !== null && InvoiceStatus::parse($invoice->TrangThai) === InvoiceStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'invoice_id' => 'Không thể thu tiền cho hóa đơn đã hủy.',
                ]);
            }

            $data['order_id'] = $targetOrderId;
            $attributes = $this->mapInput($data);
            $this->validateAmount($lockedOrder, $invoice, $attributes);

            if ($this->hasSuccessfulPayment($lockedOrder)) {
                throw ValidationException::withMessages([
                    'amount' => 'Đơn hàng đã có giao dịch thu thành công; không thể thu thêm lần nữa.',
                ]);
            }

            $payment = ThanhToan::create($attributes);
            $this->recordCollection($payment);

            $order = $payment->donHang;

            if ($invoice !== null) {
                $this->updateInvoiceStatus($invoice);
            }

            if ($order && PaymentStatus::parse($payment->TrangThai)->isPaid()) {
                $this->orderService->awardPointsForSettledDeliveredOrder($order);
            }

            return $payment->fresh();
        });
    }

    public function update(ThanhToan $payment, array $data, bool $override = false): ThanhToan
    {
        $orderId = (int) $payment->DonHangID;

        return DB::transaction(function () use ($payment, $data, $override, $orderId): ThanhToan {
            $lockedOrder = DonHang::query()->lockForUpdate()->findOrFail($orderId);
            $payment = ThanhToan::query()->lockForUpdate()->findOrFail($payment->getKey());
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

            $invoice = $this->lockInvoice($lockedOrder, $data['invoice_id'] ?? null);

            $this->guardImmutableCollectedPayment($payment);
            if (! $override) {
                $this->guardSettledPayment($payment);
            }

            $attributes = $this->mapInput($data);
            if (! array_key_exists('TrangThai', $data) && ! array_key_exists('status', $data)) {
                unset($attributes['TrangThai']);
            }

            $paymentAttributes = array_merge($payment->getAttributes(), $attributes);
            $this->validateAmount($lockedOrder, $invoice, $paymentAttributes);
            $newStatus = PaymentStatus::parse($paymentAttributes['TrangThai'] ?? $payment->TrangThai);
            if (
                $invoice !== null
                && InvoiceStatus::parse($invoice->TrangThai) === InvoiceStatus::Cancelled
                && $newStatus->isPaid()
            ) {
                throw ValidationException::withMessages([
                    'invoice_id' => 'Không thể thu tiền cho hóa đơn đã hủy.',
                ]);
            }
            if ($newStatus->isPaid() && $this->hasAnotherSuccessfulPayment($lockedOrder, (int) $payment->getKey())) {
                throw ValidationException::withMessages([
                    'amount' => 'Đơn hàng đã có giao dịch thu thành công; không thể ghi nhận thêm lần nữa.',
                ]);
            }

            $payment->update($attributes);
            $this->recordCollection($payment);

            if ($invoice) {
                $this->updateInvoiceStatus($invoice);
            }

            if ($newStatus->isPaid()) {
                $this->orderService->awardPointsForSettledDeliveredOrder($lockedOrder);
            }

            return $payment->fresh();
        });
    }

    private function recordCollection(ThanhToan $payment): void
    {
        if (! $payment->isSettled()) {
            return;
        }

        NhatKyHeThong::firstOrCreate([
            'HanhDong' => ThanhToan::COLLECTION_AUDIT_ACTION,
            'BangDuLieu' => 'ThanhToan', 'BanGhiID' => $payment->getKey(),
        ], [
            'TaiKhoanID' => auth()->id(),
            'DuLieuMoi' => $payment->only(['DonHangID', 'SoTien', 'PhuongThuc', 'MaGiaoDich']),
            'ThoiGian' => now(),
        ]);
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

    private function guardImmutableCollectedPayment(ThanhToan $payment): void
    {
        if ($payment->isFinanciallyFinal()) {
            throw SettledOrderException::forPayment(
                $payment->MaGiaoDich ?: ('#'.$payment->ThanhToanID)
            );
        }
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

        if ($payment->isFinanciallyFinal()) {
            throw SettledOrderException::forPayment(
                $payment->MaGiaoDich ?: ('#'.$payment->ThanhToanID)
            );
        }

        if ($payment->donHang?->isLocked()) {
            throw SettledOrderException::forOrder($payment->donHang->MaDonHang);
        }
    }

    /**
     * Tổng tiền đã ghi nhận của đơn. Bản ghi đã xoá mềm không phải tiền trong quỹ
     * nên bị loại (quan hệ Order::payments() nạp cả bản ghi đã xoá để hiển thị).
     */
    private function lockInvoice(DonHang $order, int|string|null $invoiceId): ?HoaDon
    {
        $invoice = $invoiceId !== null
            ? HoaDon::query()->lockForUpdate()->findOrFail($invoiceId)
            : $order->hoaDons()->orderBy('HoaDonID')->lockForUpdate()->first();

        if ($invoice !== null && (int) $invoice->DonHangID !== (int) $order->DonHangID) {
            throw ValidationException::withMessages([
                'invoice_id' => 'Hóa đơn không thuộc đơn hàng đã chọn.',
            ]);
        }

        return $invoice;
    }

    private function validateAmount(DonHang $order, ?HoaDon $invoice, array $attributes): void
    {
        if ($order->statusEnum() === OrderStatus::Cancelled) {
            throw ValidationException::withMessages(['order_id' => 'Không thể thu tiền cho đơn đã hủy.']);
        }

        $amount = (float) ($attributes['SoTien'] ?? 0);
        $grandTotal = (float) ($invoice?->ThanhTien ?? $order->ThanhTien);
        if ($amount <= 0 || round($amount, 2) !== round($grandTotal, 2)) {
            throw ValidationException::withMessages([
                'amount' => 'Số tiền thanh toán phải bằng toàn bộ số tiền phải trả ('.number_format($grandTotal).' đ).',
            ]);
        }
    }

    private function hasSuccessfulPayment(DonHang $order): bool
    {
        return $order->thanhToans()
            ->where('TrangThai', PaymentStatus::Paid->value)
            ->exists();
    }

    private function hasAnotherSuccessfulPayment(DonHang $order, int $paymentId): bool
    {
        return $order->thanhToans()
            ->where('TrangThai', PaymentStatus::Paid->value)
            ->where('ThanhToanID', '!=', $paymentId)
            ->exists();
    }

    private function updateInvoiceStatus(HoaDon $invoice): void
    {
        if (InvoiceStatus::parse($invoice->TrangThai) === InvoiceStatus::Cancelled) {
            return;
        }

        $donHang = $invoice->donHang;
        $payments = $donHang?->thanhToans()
            ->where('TrangThai', PaymentStatus::Paid->value);
        $paymentCount = $payments?->count() ?? 0;
        $totalPaid = (float) ($payments?->sum('SoTien') ?? 0);
        $isFullyPaid = $paymentCount === 1 && round($totalPaid, 2) === round((float) $invoice->ThanhTien, 2);

        if ($isFullyPaid) {
            $invoice->update(['TrangThai' => InvoiceStatus::Paid->value]);
        } else {
            $invoice->update(['TrangThai' => InvoiceStatus::Unpaid->value]);
        }
    }

    public function delete(ThanhToan $payment, bool $override = false): bool
    {
        return DB::transaction(function () use ($payment, $override): bool {
            $order = DonHang::query()->lockForUpdate()->findOrFail($payment->DonHangID);
            $lockedPayment = ThanhToan::query()->lockForUpdate()->findOrFail($payment->getKey());

            $this->guardImmutableCollectedPayment($lockedPayment);
            if (! $override) {
                $this->guardSettledPayment($lockedPayment);
            }

            $invoice = $order->hoaDons()->orderBy('HoaDonID')->lockForUpdate()->first();
            $result = $lockedPayment->delete();

            if ($invoice !== null) {
                $this->updateInvoiceStatus($invoice);
            }

            return $result;
        });
    }
}
