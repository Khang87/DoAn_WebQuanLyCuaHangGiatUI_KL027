<?php

namespace App\Observers;

use App\Enums\DeliveryStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
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
            app(OrderService::class)->awardPointsForSettledDeliveredOrder($order);
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
}
