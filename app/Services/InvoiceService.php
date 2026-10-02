<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Exceptions\SettledOrderException;
use App\Models\DonHang;
use App\Models\HoaDon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = HoaDon::query();

        if (! empty($filters['order_id'])) {
            $query->where('DonHangID', $filters['order_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('TrangThai', $filters['status']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('NgayLap', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('NgayLap', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('MaHoaDon', 'LIKE', '%'.$filters['search'].'%')
                    ->orWhere('GhiChu', 'LIKE', '%'.$filters['search'].'%')
                    ->orWhereHas('donHang.khachHang', fn ($sub) => $sub->where('HoTen', 'LIKE', '%'.$filters['search'].'%'));
            });
        }

        return $query->with('donHang.khachHang')->orderBy('NgayLap', 'desc')->paginate(10);
    }

    public function find(int|string $id): ?HoaDon
    {
        return HoaDon::query()
            ->where('HoaDonID', $id)
            ->orWhere('MaHoaDon', $id)
            ->first();
    }

    public function findDetailed(int|string $id): ?HoaDon
    {
        return HoaDon::query()
            ->where('HoaDonID', $id)
            ->orWhere('MaHoaDon', $id)
            ->first()
            ?->load([
                'donHang.khachHang',
                'donHang.chiTietDonHangs.dichVu',
                'donHang.chiTietDonHangs.loaiDoGiat',
                'donHang.thanhToans',
                'lichSuThayDoiHoaDons.taiKhoan',
            ]);
    }

    public function create(array $data): HoaDon
    {
        $orderId = $data['order_id'] ?? $data['DonHangID'] ?? null;
        if (! empty($orderId) && DonHang::find($orderId)?->isLocked()) {
            throw SettledOrderException::forOrder($orderId);
        }

        return DB::transaction(function () use ($data, $orderId) {
            if (empty($data['MaHoaDon']) && empty($data['code'])) {
                $data['MaHoaDon'] = 'HD'.str_pad((string) (HoaDon::max('HoaDonID') ?? 0) + 1, 3, '0', STR_PAD_LEFT);
            }

            $data['DonHangID'] = $orderId;
            $data['MaHoaDon'] = $data['MaHoaDon'] ?? $data['code'] ?? null;
            $data['NgayLap'] = $data['NgayLap'] ?? $data['invoice_date'] ?? now();
            $data['TrangThai'] = InvoiceStatus::parse($data['TrangThai'] ?? $data['status'] ?? InvoiceStatus::Unpaid->value)->value;
            $data['GhiChu'] = $data['GhiChu'] ?? $data['notes'] ?? $invoice->GhiChu;

            $data = $this->normalizeAmounts($data);

            $invoice = HoaDon::create($data);

            return $invoice->fresh();
        });
    }

    public function createFromOrder(DonHang $order): HoaDon
    {
        return DB::transaction(function () use ($order) {
            $existingInvoice = $order->hoaDons()->first();
            if ($existingInvoice) {
                return $existingInvoice->fresh();
            }

            $invoice = HoaDon::create([
                'DonHangID' => $order->DonHangID,
                'MaHoaDon' => 'HD'.str_pad((string) (HoaDon::max('HoaDonID') ?? 0) + 1, 3, '0', STR_PAD_LEFT),
                'NgayLap' => now()->toDateString(),
                'TongTien' => $order->TongTien,
                'GiamGia' => $order->discount_by_promotion + $order->discount_by_points,
                'PhiGiaoHang' => 0,
                'ThanhTien' => $order->ThanhTien,
                'TrangThai' => InvoiceStatus::Unpaid->value,
                'GhiChu' => $order->GhiChu,
            ]);

            return $invoice->fresh();
        });
    }

    public function update(HoaDon $invoice, array $data, bool $overrideSettled = false): HoaDon
    {
        $data['TrangThai'] = InvoiceStatus::parse($data['TrangThai'] ?? $data['status'] ?? $invoice->TrangThai)->value;
        $data['NgayLap'] = $data['NgayLap'] ?? $data['invoice_date'] ?? $invoice->NgayLap;
        $data['GhiChu'] = $data['GhiChu'] ?? $data['notes'] ?? null;
        $this->guardSettledInvoice($invoice, $data, $overrideSettled);

        $data = $this->normalizeAmounts($data, $invoice);

        $invoice->update($data);

        return $invoice->fresh();
    }

    /**
     * HÓA ĐƠN ĐÃ THANH TOÁN LÀ CHỐT SỐ TIỀN: CHỈ CHO SỬA GHI CHÚ, MỌI THAY ĐỔI
     * VỀ KHOẢN TIỀN HOẶC ĐÁNH DẤU LẠI LÀ CHƯA THANH TOÁN ĐƯỢC BỊ TỪ CHỐI.
     *
     * @param  array<string, mixed>  $data
     */
    private function guardSettledInvoice(HoaDon $invoice, array $data, bool $overrideSettled = false): void
    {
        if ($overrideSettled || ! $invoice->isPaid()) {
            return;
        }

        if (array_key_exists('TrangThai', $data)
            && $data['TrangThai'] !== null
            && InvoiceStatus::parse($data['TrangThai']) !== InvoiceStatus::Paid) {
            throw SettledOrderException::forInvoice($invoice->MaHoaDon);
        }

        $columnMap = [
            'total' => 'ThanhTien',
            'total_amount' => 'TongTien',
            'discount_amount' => 'GiamGia',
            'delivery_fee' => 'PhiGiaoHang',
            'grand_total' => 'ThanhTien',
            'TongTien' => 'TongTien',
            'GiamGia' => 'GiamGia',
            'PhiGiaoHang' => 'PhiGiaoHang',
            'ThanhTien' => 'ThanhTien',
        ];

        foreach (array_keys($columnMap) as $key) {
            if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
                continue;
            }

            $column = $columnMap[$key] ?? $key;
            if ((float) $data[$key] !== (float) $invoice->{$column}) {
                throw SettledOrderException::forInvoice($invoice->MaHoaDon);
            }
        }
    }

    public function updateStatus(HoaDon $invoice, string $status, bool $overrideSettled = false): HoaDon
    {
        if (! in_array($status, InvoiceStatus::values(), true)) {
            throw new \InvalidArgumentException('Trạng thái không hợp lệ');
        }

        // ĐÃ THANH TOÁN THÌ KHÔNG ĐƯỢC ĐÁNH DẤU LẠI LÀ CHƯA/CHƯA ĐỦ THANH TOÁN.
        if (! $overrideSettled && $invoice->isPaid() && InvoiceStatus::parse($status) !== InvoiceStatus::Paid) {
            throw SettledOrderException::forInvoice($invoice->MaHoaDon);
        }

        $invoice->update(['TrangThai' => $status]);

        return $invoice->fresh();
    }

    /**
     * CHUẨN HÓA BỘ SỐ TIỀN CỦA HÓA ĐƠN.
     *
     * Form ĐĂNG TẢI DÙNG `total` (SỐ TIỀN PHẢI TRẢ) TRONG KHI CÁC CỘT
     * total_amount / discount_amount / delivery_fee / grand_total PHỤC VỤ BÁO CÁO.
     * HAI NHÓM NÀY ĐƯỢC ĐỒNG BỘ QUA ĐÂY ĐỂ KHÔNG LỆCH NHAU.
     */
    private function normalizeAmounts(array $data, ?HoaDon $invoice = null): array
    {
        $totalAmount = $data['TongTien'] ?? $data['total_amount'] ?? $invoice?->TongTien;
        $discount = $data['GiamGia'] ?? $data['discount_amount'] ?? $invoice?->GiamGia ?? 0;
        $deliveryFee = $data['PhiGiaoHang'] ?? $data['delivery_fee'] ?? $invoice?->PhiGiaoHang ?? 0;

        $totalInput = $data['ThanhTien'] ?? $data['grand_total'] ?? $data['total'] ?? null;
        $total = $totalInput !== null && $totalInput !== ''
            ? (float) $totalInput
            : (float) ($totalAmount ?? 0) - (float) $discount + (float) $deliveryFee;

        $data['TongTien'] = max(0, round((float) ($totalAmount ?? $total), 2));
        $data['ThanhTien'] = max(0, round($total, 2));

        $data['GiamGia'] = (float) $discount;
        $data['PhiGiaoHang'] = (float) $deliveryFee;
        unset(
            $data['order_id'],
            $data['code'],
            $data['invoice_date'],
            $data['total'],
            $data['total_amount'],
            $data['discount_amount'],
            $data['delivery_fee'],
            $data['grand_total'],
            $data['status'],
            $data['notes']
        );

        return $data;
    }

    public function delete(HoaDon $invoice, bool $overrideSettled = false): bool
    {
        if (! $overrideSettled && $invoice->isPaid()) {
            throw SettledOrderException::forInvoice($invoice->MaHoaDon);
        }

        return $invoice->delete();
    }

    public function getTotalRevenue(): float
    {
        return (float) HoaDon::whereIn('TrangThai', InvoiceStatus::paidValues())->sum('ThanhTien');
    }
}
