<?php

namespace App\Services;

use App\Models\DonHang;
use App\Models\TinNhan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MessageService
{
    public function getOrders(): LengthAwarePaginator
    {
        $activity = TinNhan::query()->select('DonHangID')->selectRaw('MAX("ThoiGianGui") AS last_message_at, MAX("TinNhanID") AS last_message_id')
            ->whereNotNull('DonHangID')->groupBy('DonHangID');
        $unread = $this->unreadIncoming()->select('DonHangID')->selectRaw('COUNT(*) AS unread_count')
            ->whereNotNull('DonHangID')->groupBy('DonHangID');

        return DonHang::query()->with(['khachHang.taiKhoan'])->select('DonHang.*')
            ->leftJoinSub($activity, 'activity', 'activity.DonHangID', '=', 'DonHang.DonHangID')
            ->leftJoinSub($unread, 'unread', 'unread.DonHangID', '=', 'DonHang.DonHangID')
            ->addSelect('activity.last_message_at', 'unread.unread_count')
            ->orderByRaw('CASE WHEN activity.last_message_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('activity.last_message_at')->orderByDesc('activity.last_message_id')->orderByDesc('DonHang.NgayTao')->orderByDesc('DonHang.DonHangID')
            ->paginate(20)->withQueryString();
    }

    private function unreadIncoming(): Builder
    {
        return TinNhan::query()->whereIn('TrangThai', ['Đã gửi', 'Đã nhận'])
            ->whereHas('sender', fn ($query) => $query->whereNotNull('KhachHangID')->whereNull('NhanVienID'))
            ->whereHas('recipient', fn ($query) => $query->whereNotNull('NhanVienID'));
    }

    public function markRead(?int $orderId, ?int $customerId, array $messageIds): int
    {
        $boundary = TinNhan::query()->whereIn('TinNhanID', $messageIds);
        if ($orderId !== null) {
            $boundary->where('DonHangID', $orderId);
        } else {
            $boundary->whereNull('DonHangID')->where(fn ($query) => $query
                ->where('NguoiGuiID', $customerId)->orWhere('NguoiNhanID', $customerId));
        }
        $lastSeenId = $boundary->max('TinNhanID');
        if ($lastSeenId === null) {
            return 0;
        }
        // Thread read status includes older IDs; subsequent messages with higher IDs remain unread.
        $query = $this->unreadIncoming()->where('TinNhanID', '<=', $lastSeenId);
        if ($orderId !== null) {
            $query->where('DonHangID', $orderId);
        } else {
            $query->whereNull('DonHangID')->where('NguoiGuiID', $customerId);
        }

        return $query->update(['TrangThai' => 'Đã đọc']);
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

    public function sentAt(TinNhan $message): ?Carbon
    {
        $timestamp = $message->getRawOriginal('ThoiGianGui');

        if (! is_string($timestamp) || $timestamp === '') {
            return null;
        }

        return Carbon::parse($timestamp, 'UTC')->setTimezone(config('app.timezone'));
    }

    public function sendSupport(User $customer, User $sender, string $content): TinNhan
    {
        return TinNhan::query()->create([
            'NguoiGuiID' => $sender->getKey(), 'NguoiNhanID' => $customer->getKey(),
            'DonHangID' => null, 'NoiDung' => trim($content), 'ThoiGianGui' => now('UTC'), 'TrangThai' => 'Đã gửi',
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
            'ThoiGianGui' => now('UTC'),
            'TrangThai' => 'Đã gửi',
        ]);
    }
}
