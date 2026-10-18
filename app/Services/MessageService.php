<?php

namespace App\Services;

use App\Models\DonHang;
use App\Models\TinNhan;
use App\Models\User;
use App\Support\InternalCommunicationPolicy;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MessageService
{
    public function getOrders(?string $search = null): LengthAwarePaginator
    {
        $activity = TinNhan::query()->select('DonHangID')->selectRaw('MAX("ThoiGianGui") AS last_message_at, MAX("TinNhanID") AS last_message_id')
            ->whereNotNull('DonHangID')->groupBy('DonHangID');
        $unread = $this->unreadIncoming()->select('DonHangID')->selectRaw('COUNT(*) AS unread_count')
            ->whereNotNull('DonHangID')->groupBy('DonHangID');

        $orders = DonHang::query()->with(['khachHang.taiKhoan'])->select('DonHang.*')
            ->leftJoinSub($activity, 'activity', 'activity.DonHangID', '=', 'DonHang.DonHangID')
            ->leftJoinSub($unread, 'unread', 'unread.DonHangID', '=', 'DonHang.DonHangID')
            ->addSelect('activity.last_message_at', 'unread.unread_count')
            ->addSelect(['last_message_preview' => TinNhan::query()->select('NoiDung')
                ->whereColumn('TinNhan.DonHangID', 'DonHang.DonHangID')
                ->orderByDesc('ThoiGianGui')->orderByDesc('TinNhanID')->limit(1)]);

        if ($search !== null && $search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $orders->where(fn ($query) => $query
                ->whereRaw('LOWER("DonHang"."MaDonHang") LIKE ?', [$term])
                ->orWhereHas('khachHang', fn ($customer) => $customer
                    ->whereRaw('LOWER("HoTen") LIKE ?', [$term])
                    ->orWhere('SoDienThoai', 'like', '%'.$search.'%')));
        }

        $page = $orders
            ->orderByRaw('CASE WHEN activity.last_message_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('activity.last_message_at')->orderByDesc('activity.last_message_id')->orderByDesc('DonHang.NgayTao')->orderByDesc('DonHang.DonHangID')
            ->paginate(20)->withQueryString();
        $latestIds = $page->getCollection()->pluck('last_message_id')->filter()->map(fn ($id): int => (int) $id);
        if ($latestIds->isNotEmpty()) {
            $latest = $this->presentWebMessages(TinNhan::query()->whereIn('TinNhanID', $latestIds)->get())->keyBy('TinNhanID');
            foreach ($page->getCollection() as $order) {
                $message = $latest->get((int) $order->last_message_id);
                if ($message) {
                    $content = (string) $message->NoiDung;
                    $order->setAttribute('last_message_preview', str_starts_with($content, 'image:') ? '📷 Ảnh' : $content);
                }
            }
        }

        return $page;
    }

    private function unreadIncoming(): Builder
    {
        return TinNhan::query()->whereIn('TrangThai', ['Đã gửi', 'Đã nhận'])
            ->whereHas('sender', fn ($query) => $query->whereNotNull('KhachHangID')->whereNull('NhanVienID'))
            ->whereHas('recipient', fn ($query) => InternalCommunicationPolicy::scopeAccounts($query));
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
        $messages = TinNhan::query()
            ->with(['sender.khachHang', 'sender.nhanVien'])
            ->where('DonHangID', $order->DonHangID)
            ->when(Schema::hasTable('TinNhanAn'), fn ($query) => $query->whereNotExists(fn ($hidden) => $hidden
                ->selectRaw('1')->from('TinNhanAn')->whereColumn('TinNhanAn.TinNhanID', 'TinNhan.TinNhanID')
                ->where('TinNhanAn.TaiKhoanID', auth()->id())))
            ->orderByDesc('ThoiGianGui')
            ->orderByDesc('TinNhanID')
            ->limit(100)
            ->get()
            ->sortBy([
                ['ThoiGianGui', 'asc'],
                ['TinNhanID', 'asc'],
            ])
            ->values();

        return $this->presentWebMessages($messages);
    }

    public function supportCustomers(?string $search = null): LengthAwarePaginator
    {
        $customers = User::query()->with('khachHang')->whereNotNull('KhachHangID')
            ->whereNull('NhanVienID')->where('TrangThai', 'Hoạt động');

        if ($search !== null && $search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $customers->where(fn ($query) => $query
                ->whereRaw('LOWER("TenDangNhap") LIKE ?', [$term])
                ->orWhereHas('khachHang', fn ($customer) => $customer
                    ->whereRaw('LOWER("HoTen") LIKE ?', [$term])
                    ->orWhere('SoDienThoai', 'like', '%'.$search.'%')));
        }

        $supportMessage = fn () => TinNhan::query()->whereNull('DonHangID')
            ->where(fn ($query) => $query
                ->where(fn ($incoming) => $incoming->whereColumn('NguoiGuiID', 'TaiKhoan.TaiKhoanID')
                    ->whereHas('recipient', fn ($staff) => InternalCommunicationPolicy::scopeAccounts($staff)))
                ->orWhere(fn ($outgoing) => $outgoing->whereColumn('NguoiNhanID', 'TaiKhoan.TaiKhoanID')
                    ->whereHas('sender', fn ($staff) => InternalCommunicationPolicy::scopeAccounts($staff))))
            ->orderByDesc('ThoiGianGui')->orderByDesc('TinNhanID')->limit(1);

        $page = $customers->addSelect(['TaiKhoan.*',
            'last_message_preview' => $supportMessage()->select('NoiDung'),
            'last_message_at' => $supportMessage()->select('ThoiGianGui'),
            'last_message_id' => $supportMessage()->select('TinNhanID'),
        ])->orderByDesc('TaiKhoanID')->paginate(20, ['*'], 'support_page')->withQueryString();
        $latestIds = $page->getCollection()->pluck('last_message_id')->filter()->map(fn ($id): int => (int) $id);
        if ($latestIds->isNotEmpty()) {
            $latest = $this->presentWebMessages(TinNhan::query()->whereIn('TinNhanID', $latestIds)->get())->keyBy('TinNhanID');
            foreach ($page->getCollection() as $customer) {
                $message = $latest->get((int) $customer->last_message_id);
                if ($message) {
                    $content = (string) $message->NoiDung;
                    $customer->setAttribute('last_message_preview', str_starts_with($content, 'image:') ? '📷 Ảnh' : $content);
                }
            }
        }

        return $page;
    }

    public function supportCustomer(int $id): User
    {
        return User::query()->with('khachHang')->whereNotNull('KhachHangID')
            ->whereNull('NhanVienID')->where('TrangThai', 'Hoạt động')->findOrFail($id);
    }

    public function supportMessages(User $customer): Collection
    {
        $messages = TinNhan::query()->with(['sender.khachHang', 'sender.nhanVien'])
            ->whereNull('DonHangID')->where(fn ($query) => $query
            ->where(fn ($incoming) => $incoming->where('NguoiGuiID', $customer->getKey())
                ->whereHas('recipient', fn ($staff) => InternalCommunicationPolicy::scopeAccounts($staff)))
            ->orWhere(fn ($outgoing) => $outgoing->where('NguoiNhanID', $customer->getKey())
                ->whereHas('sender', fn ($staff) => InternalCommunicationPolicy::scopeAccounts($staff))))
            ->when(Schema::hasTable('TinNhanAn'), fn ($query) => $query->whereNotExists(fn ($hidden) => $hidden
                ->selectRaw('1')->from('TinNhanAn')->whereColumn('TinNhanAn.TinNhanID', 'TinNhan.TinNhanID')
                ->where('TinNhanAn.TaiKhoanID', auth()->id())))
            ->orderByDesc('ThoiGianGui')->orderByDesc('TinNhanID')->limit(100)->get()
            ->sortBy([['ThoiGianGui', 'asc'], ['TinNhanID', 'asc']])->values();

        return $this->presentWebMessages($messages);
    }

    /** @param Collection<int, TinNhan> $messages @return Collection<int, TinNhan> */
    private function presentWebMessages(Collection $messages): Collection
    {
        $needsPresentation = $messages->filter(fn (TinNhan $message): bool =>
            (int) $message->MaHoaPhienBan === 1 || str_starts_with((string) $message->NoiDung, 'image:'));
        if ($needsPresentation->isEmpty()) {
            return $messages;
        }
        $projectUrl = rtrim((string) config('services.supabase.project_url'), '/');
        $serviceKey = (string) config('services.supabase.service_role_key');
        if ($projectUrl === '' || $serviceKey === '') {
            foreach ($needsPresentation as $message) {
                $message->setAttribute('NoiDung', 'Tin nhắn được mã hóa. Cần cấu hình SUPABASE_SERVICE_ROLE_KEY để xem nội dung.');
                $message->setAttribute('ImageUrl', null);
            }

            return $messages;
        }
        $response = Http::acceptJson()->withHeaders(['apikey' => $serviceKey])->withToken($serviceKey)
            ->timeout(20)->post($projectUrl.'/functions/v1/chat-encryption', [
                'action' => 'present_web_messages', 'message_ids' => $needsPresentation->map(fn (TinNhan $message): int => (int) $message->getKey())->all(),
            ])->throw()->json('messages');
        if (! is_array($response)) {
            throw new \RuntimeException('Chat message presentation returned an invalid response.');
        }
        $presentedById = collect($response)->keyBy(fn (array $row): int => (int) ($row['TinNhanID'] ?? 0));
        foreach ($needsPresentation as $message) {
            $row = $presentedById->get((int) $message->getKey());
            if (is_array($row)) {
                $message->setAttribute('NoiDung', (string) ($row['NoiDung'] ?? ''));
                $message->setAttribute('ImageUrl', $row['ImageUrl'] ?? null);
            }
        }

        return $messages;
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

    public function sendSupportImage(User $customer, User $sender, UploadedFile $image): TinNhan
    {
        return $this->sendImage($sender, $customer, null, $image);
    }

    public function sendOrderImage(DonHang $order, User $sender, UploadedFile $image): TinNhan
    {
        $recipient = $order->khachHang?->taiKhoan;
        if (! $recipient) {
            throw ValidationException::withMessages(['message' => 'Khách hàng của đơn hàng chưa có tài khoản nhận tin nhắn.']);
        }

        return $this->sendImage($sender, $recipient, (int) $order->getKey(), $image);
    }

    private function sendImage(User $sender, User $recipient, ?int $orderId, UploadedFile $image): TinNhan
    {
        $path = (int) $sender->getKey().'/'.bin2hex(random_bytes(16)).'.'.$image->extension();
        $this->storageRequest('POST', '/storage/v1/object/chat-images/'.$this->encodeStoragePath($path), $image->getContent(), [
            'Content-Type' => (string) $image->getMimeType(), 'x-upsert' => 'false',
        ])->throw();
        try {
            return TinNhan::query()->create([
                'NguoiGuiID' => $sender->getKey(), 'NguoiNhanID' => $recipient->getKey(), 'DonHangID' => $orderId,
                'NoiDung' => 'image:'.$path, 'ThoiGianGui' => now('UTC'), 'TrangThai' => 'Đã gửi',
            ]);
        } catch (\Throwable $exception) {
            $this->storageRequest('DELETE', '/storage/v1/object/chat-images/'.$this->encodeStoragePath($path))->throw();
            throw $exception;
        }
    }

    public function recall(TinNhan $message): void
    {
        $this->presentWebMessages(collect([$message]));
        $path = str_starts_with((string) $message->NoiDung, 'image:')
            ? substr((string) $message->NoiDung, strlen('image:')) : null;
        $message->delete();
        if ($path !== null) {
            $this->storageRequest('DELETE', '/storage/v1/object/chat-images/'.$this->encodeStoragePath($path))->throw();
        }
    }

    private function storageRequest(string $method, string $path, ?string $body = null, array $headers = []): \Illuminate\Http\Client\Response
    {
        $projectUrl = rtrim((string) config('services.supabase.project_url'), '/');
        $serviceKey = (string) config('services.supabase.service_role_key');
        if ($projectUrl === '' || $serviceKey === '') {
            throw new \RuntimeException('Chat image storage is not configured.');
        }
        $request = Http::withHeaders(['apikey' => $serviceKey])->withToken($serviceKey)->withHeaders($headers)->timeout(30);

        return $method === 'POST'
            ? $request->withBody($body ?? '', $headers['Content-Type'] ?? 'application/octet-stream')->post($projectUrl.$path)
            : $request->delete($projectUrl.$path);
    }

    private function encodeStoragePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
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
