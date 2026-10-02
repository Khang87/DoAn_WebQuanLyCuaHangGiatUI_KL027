<?php

namespace App\Services;

use App\Models\DonHang;
use App\Models\TinNhan;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MessageService
{
    public function getOrders(): LengthAwarePaginator
    {
        return DonHang::query()
            ->with(['khachHang.taiKhoan'])
            ->orderByDesc('NgayTao')
            ->orderByDesc('DonHangID')
            ->paginate(20)
            ->withQueryString();
    }

    public function findOrder(int $orderId): ?DonHang
    {
        return DonHang::query()
            ->with(['khachHang.taiKhoan'])
            ->find($orderId);
    }

    /**
     * Load a bounded recent history and present it in chronological order.
     *
     * @return Collection<int, TinNhan>
     */
    public function getMessages(DonHang $order): Collection
    {
        return TinNhan::query()
            ->with('sender')
            ->where('DonHangID', $order->DonHangID)
            ->orderByDesc('ThoiGianGui')
            ->orderByDesc('TinNhanID')
            ->limit(100)
            ->get()
            ->sortBy([
                ['ThoiGianGui', 'asc'],
                ['TinNhanID', 'asc'],
            ])
            ->values();
    }

    public function sendFromStore(DonHang $order, User $sender, string $content): TinNhan
    {
        $recipientId = $order->khachHang?->taiKhoan?->TaiKhoanID;

        if (! $recipientId) {
            throw ValidationException::withMessages([
                'message' => 'Khách hàng của đơn hàng chưa có tài khoản nhận tin nhắn.',
            ]);
        }

        return TinNhan::query()->create([
            'NguoiGuiID' => $sender->getKey(),
            'NguoiNhanID' => $recipientId,
            'DonHangID' => $order->DonHangID,
            'NoiDung' => trim($content),
            'ThoiGianGui' => now(),
            'TrangThai' => 'Đã gửi',
        ]);
    }
}
