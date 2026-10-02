<?php

namespace App\Observers;

use App\Models\DonHang;
use App\Models\NhatKyHeThong;
use Illuminate\Support\Facades\Auth;
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
