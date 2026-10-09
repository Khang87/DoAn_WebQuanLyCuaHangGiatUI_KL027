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
            ->with(['sender.khachHang', 'sender.nhanVien'])
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

    public function supportCustomers(): LengthAwarePaginator
    {
        return User::query()->with('khachHang')->whereNotNull('KhachHangID')
            ->whereNull('NhanVienID')->where('TrangThai', 'Hoạt động')
            ->orderByDesc('TaiKhoanID')->paginate(20, ['*'], 'support_page')->withQueryString();
    }

    public function supportCustomer(int $id): User
    {
        return User::query()->with('khachHang')->whereNotNull('KhachHangID')
            ->whereNull('NhanVienID')->where('TrangThai', 'Hoạt động')->findOrFail($id);
    }

    public function supportMessages(User $customer): Collection
    {
        return TinNhan::query()->with(['sender.khachHang', 'sender.nhanVien'])
            ->whereNull('DonHangID')->where(fn ($query) => $query
            ->where(fn ($incoming) => $incoming->where('NguoiGuiID', $customer->getKey())
                ->whereHas('recipient', fn ($staff) => $staff->whereNotNull('NhanVienID')))
            ->orWhere(fn ($outgoing) => $outgoing->where('NguoiNhanID', $customer->getKey())
                ->whereHas('sender', fn ($staff) => $staff->whereNotNull('NhanVienID'))))
            ->orderByDesc('ThoiGianGui')->orderByDesc('TinNhanID')->limit(100)->get()
            ->sortBy([['ThoiGianGui', 'asc'], ['TinNhanID', 'asc']])->values();
    }

    public function displayName(TinNhan $message, User $viewer): string
    {
        if ((int) $message->NguoiGuiID === (int) $viewer->getKey()) {
            return 'Cửa hàng';
        }
        $name = $message->sender?->name;

        return ! $name || str_starts_with($name, 'auth-') ? 'Khách hàng' : $name;
    }

    public function sendSupport(User $customer, User $sender, string $content): TinNhan
    {
        return TinNhan::query()->create([
            'NguoiGuiID' => $sender->getKey(), 'NguoiNhanID' => $customer->getKey(),
            'DonHangID' => null, 'NoiDung' => trim($content), 'ThoiGianGui' => now(), 'TrangThai' => 'Đã gửi',
        ]);
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
