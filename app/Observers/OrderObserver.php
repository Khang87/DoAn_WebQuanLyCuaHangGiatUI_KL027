<?php

namespace App\Observers;

use App\Enums\DeliveryStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\DonHang;
use App\Models\NhatKyHeThong;
use App\Services\OrderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class OrderObserver
{
    public function created(DonHang $order): void
    {
        $this->recordStatusChange($order, null, $order->TrangThai);
    }

    public function updated(DonHang $order): void
    {
        if (! $order->wasChanged('TrangThai')) {
            return;
        }

        $this->recordStatusChange(
            $order,
            $order->getOriginal('TrangThai'),
            $order->TrangThai,
        );

        if ($order->TrangThai === OrderStatus::Delivered->value) {
            $this->awardCompletionPoints($order);
            $this->markPaidIfFullySettled($order);
        }

        if ($order->TrangThai === OrderStatus::Cancelled->value) {
            $this->refundRedeemedPoints($order);
            $order->hoaDons()->update(['TrangThai' => InvoiceStatus::Cancelled->value]);
            $order->giaoNhans()->update(['TrangThai' => DeliveryStatus::Cancelled->dbValue()]);
        }
    }

    private function refundRedeemedPoints(DonHang $order): void
    {
        DB::transaction(function () use ($order): void {
            $locked = DonHang::query()->lockForUpdate()->findOrFail($order->getKey());
            $action = 'Hoàn điểm tích lũy đơn hàng';
            if ($locked->DiemSuDung <= 0 || NhatKyHeThong::query()
                ->where('BangDuLieu', 'DonHang')->where('BanGhiID', $locked->getKey())
                ->where('HanhDong', $action)->exists()) {
                return;
            }
            $locked->khachHang()->firstOrFail()->addPoints((int) $locked->DiemSuDung);
            NhatKyHeThong::create([
                'TaiKhoanID' => Auth::id(), 'HanhDong' => $action,
                'BangDuLieu' => 'DonHang', 'BanGhiID' => $locked->getKey(),
                'DuLieuMoi' => ['DiemHoan' => (int) $locked->DiemSuDung], 'ThoiGian' => now(),
            ]);
        });
    }

    private function recordStatusChange(DonHang $order, ?string $oldStatus, ?string $newStatus): void
    {
        if ($oldStatus === $newStatus) {
            return;
        }

        NhatKyHeThong::query()->create([
            'TaiKhoanID' => Auth::id(),
            'HanhDong' => 'Chuyển trạng thái đơn hàng',
            'BangDuLieu' => 'DonHang',
            'BanGhiID' => $order->getKey(),
            'DuLieuCu' => ['TrangThai' => $oldStatus],
            'DuLieuMoi' => ['TrangThai' => $newStatus],
            'ThoiGian' => now(),
            'IPAddress' => Request::ip(),
            'UserAgent' => Request::userAgent(),
        ]);
    }

    private function awardCompletionPoints(DonHang $order): void
    {
        DB::transaction(function () use ($order): void {
            $lockedOrder = DonHang::query()->lockForUpdate()->findOrFail($order->getKey());
            $action = 'Cộng điểm tích lũy đơn hàng';

            $alreadyAwarded = NhatKyHeThong::query()
                ->where('BangDuLieu', 'DonHang')
                ->where('BanGhiID', $lockedOrder->getKey())
                ->where('HanhDong', $action)
                ->exists();

            if ($alreadyAwarded || $lockedOrder->TrangThai !== OrderStatus::Delivered->value) {
                return;
            }

            $customer = $lockedOrder->khachHang()->firstOrFail();
            $pointsBefore = $customer->points();
            $pointsAwarded = (int) floor(
                (float) $lockedOrder->ThanhTien / OrderService::POINTS_PER_AMOUNT
            ) * OrderService::POINTS_EARNED_PER_AMOUNT;

            if ($pointsAwarded > 0) {
                $customer->addPoints($pointsAwarded);
            }

            NhatKyHeThong::query()->create([
                'TaiKhoanID' => Auth::id(),
                'HanhDong' => $action,
                'BangDuLieu' => 'DonHang',
                'BanGhiID' => $lockedOrder->getKey(),
                'DuLieuCu' => ['DiemHienTai' => $pointsBefore],
                'DuLieuMoi' => [
                    'DiemHienTai' => $pointsBefore + $pointsAwarded,
                    'DiemCong' => $pointsAwarded,
                    'ThanhTien' => (float) $lockedOrder->ThanhTien,
                ],
                'ThoiGian' => now(),
                'IPAddress' => Request::ip(),
                'UserAgent' => Request::userAgent(),
            ]);
        });
    }

    private function markPaidIfFullySettled(DonHang $order): void
    {
        $lockedOrder = DonHang::query()->with('hoaDons')->findOrFail($order->getKey());
        $grandTotal = (float) ($lockedOrder->hoaDons->first()?->ThanhTien ?? $lockedOrder->ThanhTien);
        $totalPaid = (float) $lockedOrder->thanhToans()
            ->where('TrangThai', PaymentStatus::Paid->value)
            ->sum('SoTien');

        if ($grandTotal > 0 && $totalPaid >= $grandTotal) {
            app(OrderService::class)->updateStatus($lockedOrder, OrderStatus::Paid->value);
        }
    }
}
