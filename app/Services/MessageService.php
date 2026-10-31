<?php

namespace App\Services;

use App\Models\DonHang;
use App\Models\TaiKhoan;
use App\Models\TinNhan;
use App\Models\User;
use App\Support\InternalCommunicationPolicy;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MessageService
{
    public function getOrders(?string $search = null, ?int $pageNumber = null): LengthAwarePaginator
    {
        $activity = TinNhan::query()->select('DonHangID')->selectRaw('MAX("ThoiGianGui") AS last_message_at, MAX("TinNhanID") AS last_message_id')
            ->whereNotNull('DonHangID')->groupBy('DonHangID');
        $unread = $this->unreadIncoming()->select('DonHangID')->selectRaw('COUNT(*) AS unread_count')
            ->whereNotNull('DonHangID')->groupBy('DonHangID');

        $orders = DonHang::query()->with(['khachHang.taiKhoan:TaiKhoanID,KhachHangID,AvatarURL'])->select('DonHang.*')
            ->leftJoinSub($activity, 'activity', 'activity.DonHangID', '=', 'DonHang.DonHangID')
            ->leftJoinSub($unread, 'unread', 'unread.DonHangID', '=', 'DonHang.DonHangID')
            ->addSelect('activity.last_message_at', 'activity.last_message_id', 'unread.unread_count')
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
            ->paginate(20, ['*'], 'page', $pageNumber)->withQueryString();
        $latestIds = $page->getCollection()->pluck('last_message_id')->filter()->map(fn ($id): int => (int) $id);
        if ($latestIds->isNotEmpty()) {
            $latest = $this->presentWebMessages(TinNhan::query()->whereIn('TinNhanID', $latestIds)->get(), true)->keyBy('TinNhanID');
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

    public function unreadCount(): int
    {
        return $this->unreadIncoming()->count();
    }

    /** @return array{tab: string, id: int}|null */
    public function latestUnreadConversation(): ?array
    {
        $message = $this->unreadIncoming()->orderByDesc('ThoiGianGui')->orderByDesc('TinNhanID')->first();
        if (! $message) {
            return null;
        }

        return $message->DonHangID !== null
            ? ['tab' => 'orders', 'id' => (int) $message->DonHangID]
            : ['tab' => 'support', 'id' => (int) $message->NguoiGuiID];
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
            ->with(['khachHang.taiKhoan', 'chiTietDonHangs.dichVu', 'chiTietDonHangs.loaiDoGiat', 'chiTietDonHangs.donViTinh'])
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

    public function supportCustomers(?string $search = null, ?int $pageNumber = null): LengthAwarePaginator
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
        ])->orderByRaw('CASE WHEN last_message_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('last_message_at')->orderByDesc('last_message_id')->orderByDesc('TaiKhoan.TaiKhoanID')
            ->paginate(20, ['*'], 'support_page', $pageNumber)->withQueryString();
        $latestIds = $page->getCollection()->pluck('last_message_id')->filter()->map(fn ($id): int => (int) $id);
        if ($latestIds->isNotEmpty()) {
            $latest = $this->presentWebMessages(TinNhan::query()->whereIn('TinNhanID', $latestIds)->get(), true)->keyBy('TinNhanID');
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

    /**
     * @param  Collection<int, TinNhan>  $messages
     * @return Collection<int, TinNhan>
     */
    private function presentWebMessages(Collection $messages, bool $allowDecryptionFailure = false): Collection
    {
        if ($messages->isEmpty()) {
            return $messages;
        }

        try {
            $presented = $this->invokeChatFunction([
                'action' => 'present_web_messages',
                'message_ids' => $messages->map(fn (TinNhan $message): int => (int) $message->getKey())->all(),
            ]);
            $byId = collect($presented['messages'] ?? [])->keyBy(fn (array $message): int => (int) ($message['TinNhanID'] ?? 0));
            foreach ($messages as $message) {
                $data = $byId->get((int) $message->getKey());
                if (! is_array($data)) {
                    throw new \RuntimeException('Supabase did not return a presented chat message.');
                }
                $recalled = (bool) ($data['DaThuHoi'] ?? false);
                $message->setAttribute('DaThuHoi', $recalled);
                $message->setAttribute('NoiDung', $recalled ? 'Tin nhắn đã thu hồi' : (string) ($data['NoiDung'] ?? ''));
                $message->setAttribute('ImageUrl', $recalled ? null : ($data['ImageUrl'] ?? null));
                $message->setAttribute('DaChinhSua', (bool) ($data['DaChinhSua'] ?? false));
            }
        } catch (\Throwable $exception) {
            Log::warning('Unable to present chat messages for web display.', ['exception' => $exception::class]);
            if (! $allowDecryptionFailure) {
                throw $exception;
            }
            foreach ($messages as $message) {
                $message->setAttribute('NoiDung', 'Chưa thể tải nội dung tin nhắn. Vui lòng thử lại sau.');
                $message->setAttribute('ImageUrl', null);
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
        return $this->createWebMessage($sender, $customer, null, trim($content));
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

    /** @return array{path: string, signed_url: string, token: string, api_key: string, project_url: string} */
    public function createImageUploadUrl(User $sender, string $mimeType): array
    {
        $data = $this->invokeChatFunction([
            'action' => 'create_image_upload',
            'sender_account_id' => (int) $sender->getKey(),
            'mime_type' => $mimeType,
        ]);
        if (! is_array($data) || ! is_string($data['path'] ?? null) || ! is_string($data['signed_url'] ?? null)
            || ! is_string($data['token'] ?? null) || ! is_string($data['api_key'] ?? null)) {
            throw new \RuntimeException('Chat image upload URL response is invalid.');
        }

        return [...$data, 'project_url' => rtrim((string) config('services.supabase.project_url'), '/')];
    }

    public function sendSupportImagePath(User $customer, User $sender, string $path): TinNhan
    {
        return $this->createImageMessage($sender, $customer, null, $path);
    }

    public function sendOrderImagePath(DonHang $order, User $sender, string $path): TinNhan
    {
        $recipient = $order->khachHang?->taiKhoan;
        if (! $recipient) {
            throw ValidationException::withMessages(['message' => 'Khách hàng của đơn hàng chưa có tài khoản nhận tin nhắn.']);
        }

        return $this->createImageMessage($sender, $recipient, (int) $order->getKey(), $path);
    }

    private function createImageMessage(User $sender, User|TaiKhoan $recipient, ?int $orderId, string $path): TinNhan
    {
        return $this->createWebMessage($sender, $recipient, $orderId, 'image:'.$path);
    }

    private function sendImage(User $sender, User|TaiKhoan $recipient, ?int $orderId, UploadedFile $image): TinNhan
    {
        $path = (int) $sender->getKey().'/'.bin2hex(random_bytes(16)).'.'.$image->extension();
        $this->storageRequest('POST', '/storage/v1/object/chat-images/'.$this->encodeStoragePath($path), $image->getContent(), [
            'Content-Type' => (string) $image->getMimeType(), 'x-upsert' => 'false',
        ])->throw();
        try {
            return $this->createWebMessage($sender, $recipient, $orderId, 'image:'.$path);
        } catch (\Throwable $exception) {
            try {
                $this->storageRequest('DELETE', '/storage/v1/object/chat-images/'.$this->encodeStoragePath($path))->throw();
            } catch (\Throwable $cleanupException) {
                Log::warning('Failed to clean up an unsent chat image.', [
                    'exception' => $cleanupException::class,
                ]);
            }
            throw $exception;
        }
    }

    public function recall(TinNhan $message): void
    {
        $this->invokeChatFunction([
            'action' => 'recall_message_web',
            'message_id' => (int) $message->getKey(),
            'sender_account_id' => (int) $message->NguoiGuiID,
        ]);
    }

    public function edit(TinNhan $message, string $content): void
    {
        $this->invokeChatFunction([
            'action' => 'edit_message',
            'message_id' => (int) $message->getKey(),
            'sender_account_id' => (int) $message->NguoiGuiID,
            'content' => trim($content),
        ]);
    }

    public function hideForAccount(int $messageId, int $accountId): void
    {
        $this->invokeChatFunction([
            'action' => 'hide_message_web',
            'message_id' => $messageId,
            'account_id' => $accountId,
        ]);
    }

    private function storageRequest(string $method, string $path, ?string $body = null, array $headers = []): Response
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

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    private function invokeChatFunction(array $payload): array
    {
        $projectUrl = rtrim((string) config('services.supabase.project_url'), '/');
        $serviceKey = (string) config('services.supabase.service_role_key');
        if ($projectUrl === '' || $serviceKey === '') {
            throw new \RuntimeException('Chat operations are not configured.');
        }

        $response = Http::acceptJson()->withHeaders(['apikey' => $serviceKey])->withToken($serviceKey)
            ->timeout(20)->post($projectUrl.'/functions/v1/chat-encryption', $payload);
        $data = $response->json();
        if (! $response->successful() || ! is_array($data)) {
            $message = is_array($data) && is_string($data['error'] ?? null)
                ? $data['error'] : 'Supabase chat function returned an invalid response.';
            Log::error('Supabase rejected a web chat database operation.', [
                'action' => $payload['action'] ?? 'unknown',
                'status' => $response->status(),
                'response' => $message,
            ]);
            throw new \RuntimeException('Chat action failed (HTTP '.$response->status().'): '.$message);
        }

        return $data;
    }

    private function createWebMessage(User $sender, User|TaiKhoan $recipient, ?int $orderId, string $content): TinNhan
    {
        $result = $this->invokeChatFunction([
            'action' => str_starts_with($content, 'image:') ? 'create_image_message_web' : 'send_message_web',
            'sender_account_id' => (int) $sender->getKey(),
            'recipient_account_id' => (int) $recipient->getKey(),
            'order_id' => $orderId,
            'content' => $content,
        ]);
        $messageId = filter_var($result['id'] ?? null, FILTER_VALIDATE_INT);
        if (! $messageId || $messageId < 1) {
            throw new \RuntimeException('Supabase chat function did not return the created message ID.');
        }

        return TinNhan::query()->getModel()->newFromBuilder([
            'TinNhanID' => $messageId,
            'NguoiGuiID' => $sender->getKey(),
            'NguoiNhanID' => $recipient->getKey(),
            'DonHangID' => $orderId,
            'NoiDung' => $content,
            'ThoiGianGui' => now('UTC')->format('Y-m-d H:i:s'),
            'TrangThai' => 'Đã gửi',
        ]);
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

        $recipient = $order->khachHang?->taiKhoan;
        if (! $recipient) {
            throw ValidationException::withMessages([
                'message' => 'Khách hàng của đơn hàng chưa có tài khoản nhận tin nhắn.',
            ]);
        }

        return $this->createWebMessage($sender, $recipient, (int) $order->DonHangID, trim($content));
    }
}
