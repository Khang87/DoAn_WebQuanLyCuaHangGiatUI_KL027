<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Exceptions\SettledOrderException;
use App\Models\HoaDon;
use App\Models\DonHang;
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

        return $query->with('donHang.khachHang')->latest()->paginate(10);
    }

    public function find(int|string $id): ?HoaDon
    {
        return HoaDon::withTrashed()
            ->where('id', $id)
            ->orWhere('MaHoaDon', $id)
            ->first();
    }

    public function findDetailed(int|string $id): ?HoaDon
    {
        return HoaDon::withTrashed()
            ->where('id', $id)
            ->orWhere('MaHoaDon', $id)
            ->first()
            ?->load(['donHang.khachHang', 'donHang.chiTietDonHangs.dichVu', 'donHang.chiTietDonHangs.loaiDoGiat', 'donHang.thanhToans']);
    }

    public function create(array $data): HoaDon
    {
        if (! empty($data['order_id']) && DonHang::find($data['order_id'])?->isLocked()) {
            throw SettledOrderException::forOrder($data['order_id']);
        }

        return DB::transaction(function () use ($data) {
            if (empty($data['code'])) {
                $data['code'] = 'HD'.str_pad((string) (HoaDon::max('HoaDonID') ?? 0) + 1, 3, '0', STR_PAD_LEFT);
            }

            if (empty($data['invoice_date'])) {
                $data['invoice_date'] = now()->toDateString();
            }

            $data = $this->normalizeAmounts($data);

            $invoice = HoaDon::create($data);

            if (! empty($data['order_id'])) {
                $order = DonHang::find($data['order_id']);
                if ($order) {
                    $order->update(['TrangThai' => 'completed']);
                }
            }

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
                'TongTien' => $order->total_amount,
                'GiamGia' => $order->discount_by_promotion + $order->discount_by_points,
                'PhiGiaoHang' => 0,
                'ThanhTien' => $order->total_amount,
                'TrangThai' => 'unpaid',
                'GhiChu' => $order->notes,
            ]);

            return $invoice->fresh();
        });
    }

    public function update(HoaDon $invoice, array $data, bool $overrideSettled = false): HoaDon
    {
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

        if (array_key_exists('status', $data)
            && $data['status'] !== null
            && InvoiceStatus::parse($data['status']) !== InvoiceStatus::Paid) {
            throw SettledOrderException::forInvoice($invoice->MaHoaDon);
        }

        $columnMap = [
            'total' => 'TongTien',
            'total_amount' => 'TongTien',
            'discount_amount' => 'GiamGia',
            'delivery_fee' => 'PhiGiaoHang',
            'grand_total' => 'ThanhTien',
        ];

        foreach (['total', 'total_amount', 'discount_amount', 'delivery_fee', 'grand_total'] as $key) {
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
        $totalAmount = $data['total_amount'] ?? $invoice?->TongTien;
        $discount = $data['discount_amount'] ?? $invoice?->GiamGia ?? 0;
        $deliveryFee = $data['delivery_fee'] ?? $invoice?->PhiGiaoHang ?? 0;

        $total = array_key_exists('total', $data) && $data['total'] !== null && $data['total'] !== ''
            ? (float) $data['total']
            : (float) ($totalAmount ?? 0) - (float) $discount + (float) $deliveryFee;

        $data['TongTien'] = max(0, round($total, 2));
        $data['ThanhTien'] = $data['TongTien'];

        if ($totalAmount !== null) {
            $data['TongTien'] = (float) $totalAmount;
        }

        $data['GiamGia'] = (float) $discount;
        $data['PhiGiaoHang'] = (float) $deliveryFee;

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
