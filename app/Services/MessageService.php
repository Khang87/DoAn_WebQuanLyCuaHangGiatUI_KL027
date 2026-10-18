<?php

namespace App\Services;

use App\Models\DonHang;
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
    public function __construct(
        private ChatMessageDecryptionService $messageCryptoService,
    ) {}

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
        $encrypted = $messages->filter(fn (TinNhan $message): bool => (int) $message->MaHoaPhienBan === 1);

        if ($encrypted->isNotEmpty()) {
            try {
                $this->messageCryptoService->decryptMessages($encrypted);
            } catch (ChatMessageDecryptionException $exception) {
                Log::warning('Unable to decrypt encrypted chat messages for web display.', [
                    'exception' => $exception::class,
                ]);

                if (! $allowDecryptionFailure) {
                    throw $exception;
                }

                foreach ($encrypted as $message) {
                    $message->setAttribute('NoiDung', 'Chưa thể giải mã tin nhắn. Vui lòng thử lại sau.');
                }
            }
        }

        $images = $messages->filter(fn (TinNhan $message): bool => str_starts_with((string) $message->NoiDung, 'image:'));

        if ($images->isEmpty()) {
            return $messages;
        }

        $projectUrl = rtrim((string) config('services.supabase.project_url'), '/');
        $serviceKey = (string) config('services.supabase.service_role_key');

        if ($projectUrl === '' || $serviceKey === '') {
            foreach ($images as $message) {
                $message->setAttribute('NoiDung', 'Tin nhắn có ảnh. Cần cấu hình SUPABASE_SERVICE_ROLE_KEY để xem ảnh.');
                $message->setAttribute('ImageUrl', null);
            }

            return $messages;
        }

        foreach ($images as $message) {
            $path = substr((string) $message->NoiDung, strlen('image:'));
            try {
                $response = Http::acceptJson()->withHeaders(['apikey' => $serviceKey])->withToken($serviceKey)
                    ->timeout(15)->post($projectUrl.'/storage/v1/object/sign/chat-images/'.$this->encodeStoragePath($path), [
                        'expiresIn' => 3600,
                    ])->throw();
                $signedPath = $response->json('signedURL') ?? $response->json('signedUrl');
                if (! is_string($signedPath) || $signedPath === '') {
                    throw new \RuntimeException('Storage did not return a signed image URL.');
                }
                $imageUrl = str_starts_with($signedPath, 'http')
                    ? $signedPath
                    : $projectUrl.(str_starts_with($signedPath, '/object/') ? '/storage/v1' : '').$signedPath;
                $message->setAttribute('NoiDung', 'Ảnh');
                $message->setAttribute('ImageUrl', $imageUrl);
            } catch (\Throwable $exception) {
                Log::warning('Unable to sign chat image URL.', [
                    'message_id' => (int) $message->getKey(),
                    'exception' => $exception::class,
                ]);
                $message->setAttribute('NoiDung', 'Chưa thể tải ảnh tin nhắn. Vui lòng thử lại sau.');
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
        $encrypted = $this->messageCryptoService->encryptContent(trim($content));

        return $this->createMessage([
            'NguoiGuiID' => $sender->getKey(), 'NguoiNhanID' => $customer->getKey(),
            'DonHangID' => null,
            'NoiDung' => '',
            'NoiDungMaHoa' => $encrypted['ciphertext'],
            'NoiDungNonce' => $encrypted['nonce'],
            'MaHoaPhienBan' => 1,
            'ThoiGianGui' => now('UTC'),
            'TrangThai' => 'Đã gửi',
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

    /** @return array{path: string, signed_url: string, token: string, api_key: string} */
    public function createImageUploadUrl(User $sender, string $mimeType): array
    {
        $projectUrl = rtrim((string) config('services.supabase.project_url'), '/');
        $serviceKey = (string) config('services.supabase.service_role_key');
        if ($projectUrl === '' || $serviceKey === '') {
            throw new \RuntimeException('Chat image uploads are not configured.');
        }

        $data = Http::acceptJson()->withHeaders(['apikey' => $serviceKey])->withToken($serviceKey)
            ->timeout(20)->post($projectUrl.'/functions/v1/chat-encryption', [
                'action' => 'create_image_upload', 'sender_account_id' => (int) $sender->getKey(), 'mime_type' => $mimeType,
            ])->throw()->json();
        if (! is_array($data) || ! is_string($data['path'] ?? null) || ! is_string($data['signed_url'] ?? null)
            || ! is_string($data['token'] ?? null) || ! is_string($data['api_key'] ?? null)) {
            throw new \RuntimeException('Chat image upload URL response is invalid.');
        }

        return $data;
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

    private function createImageMessage(User $sender, User $recipient, ?int $orderId, string $path): TinNhan
    {
        return $this->createMessage([
            'NguoiGuiID' => $sender->getKey(), 'NguoiNhanID' => $recipient->getKey(), 'DonHangID' => $orderId,
            'NoiDung' => 'image:'.$path, 'ThoiGianGui' => now('UTC'), 'TrangThai' => 'Đã gửi',
        ]);
    }

    private function sendImage(User $sender, User $recipient, ?int $orderId, UploadedFile $image): TinNhan
    {
        $path = (int) $sender->getKey().'/'.bin2hex(random_bytes(16)).'.'.$image->extension();
        $this->storageRequest('POST', '/storage/v1/object/chat-images/'.$this->encodeStoragePath($path), $image->getContent(), [
            'Content-Type' => (string) $image->getMimeType(), 'x-upsert' => 'false',
        ])->throw();
        try {
            return $this->createMessage([
                'NguoiGuiID' => $sender->getKey(), 'NguoiNhanID' => $recipient->getKey(), 'DonHangID' => $orderId,
                'NoiDung' => 'image:'.$path, 'ThoiGianGui' => now('UTC'), 'TrangThai' => 'Đã gửi',
            ]);
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
        $path = str_starts_with((string) $message->NoiDung, 'image:')
            ? substr((string) $message->NoiDung, strlen('image:')) : null;
        $this->postgrestRequest('DELETE', '/rest/v1/TinNhan?TinNhanID=eq.'.(int) $message->getKey()
            .'&NguoiGuiID=eq.'.(int) $message->NguoiGuiID.'&select=TinNhanID', null, 'return=representation')->throw();
        if ($path !== null) {
            try {
                $this->storageRequest('DELETE', '/storage/v1/object/chat-images/'.$this->encodeStoragePath($path))->throw();
            } catch (\Throwable $exception) {
                Log::warning('Chat message was recalled but its image could not be removed from storage.', [
                    'message_id' => (int) $message->getKey(),
                    'exception' => $exception::class,
                ]);
            }
        }
    }

    public function edit(TinNhan $message, string $content): void
    {
        if (str_starts_with((string) $message->NoiDung, 'image:')) {
            throw ValidationException::withMessages(['content' => 'Không thể sửa nội dung của tin nhắn ảnh.']);
        }
        $encrypted = $this->messageDecryptionService->encryptContent(trim($content));
        $response = $this->postgrestRequest(
            'PATCH',
            '/rest/v1/TinNhan?TinNhanID=eq.'.(int) $message->getKey()
                .'&NguoiGuiID=eq.'.(int) $message->NguoiGuiID.'&select=TinNhanID',
            [
                'NoiDung' => '',
                'NoiDungMaHoa' => $encrypted['ciphertext'],
                'NoiDungNonce' => $encrypted['nonce'],
                'MaHoaPhienBan' => 1,
                'DaChinhSua' => true,
            ],
            'return=representation',
        )->throw();
        if (! is_array($response->json()) || $response->json() === []) {
            abort(404, 'Tin nhắn không còn tồn tại.');
        }
    }

    public function hideForAccount(int $messageId, int $accountId): void
    {
        $projectUrl = rtrim((string) config('services.supabase.project_url'), '/');
        $serviceKey = (string) config('services.supabase.service_role_key');
        if ($projectUrl === '' || $serviceKey === '') {
            throw new \RuntimeException('Chat message hiding is not configured.');
        }

        $this->postgrestRequest(
            'POST',
            '/rest/v1/TinNhanAn?on_conflict=TaiKhoanID,TinNhanID',
            [[
                'TaiKhoanID' => $accountId,
                'TinNhanID' => $messageId,
                'ThoiGianAn' => now('UTC')->format('Y-m-d H:i:s'),
            ]],
            'resolution=ignore-duplicates,return=minimal',
        )->throw();
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

    /** @param array<string, mixed>|list<array<string, mixed>>|null $payload */
    private function postgrestRequest(string $method, string $path, ?array $payload = null, ?string $prefer = null): \Illuminate\Http\Client\Response
    {
        $projectUrl = rtrim((string) config('services.supabase.project_url'), '/');
        $serviceKey = (string) config('services.supabase.service_role_key');
        if ($projectUrl === '' || $serviceKey === '') {
            throw new \RuntimeException('Chat database operations are not configured.');
        }

        $headers = ['apikey' => $serviceKey];
        if ($prefer !== null) {
            $headers['Prefer'] = $prefer;
        }
        $request = Http::acceptJson()->withHeaders($headers)->withToken($serviceKey)->timeout(20);
        $url = $projectUrl.$path;
        $response = match (strtoupper($method)) {
            'POST' => $request->post($url, $payload ?? []),
            'PATCH' => $request->patch($url, $payload ?? []),
            'DELETE' => $request->delete($url),
            default => throw new \InvalidArgumentException('Unsupported chat database operation.'),
        };

        if ($response->failed()) {
            Log::error('Supabase rejected a web chat database operation.', [
                'method' => strtoupper($method),
                'resource' => strtok($path, '?'),
                'status' => $response->status(),
                'response' => mb_substr($response->body(), 0, 500),
            ]);
        }

        return $response;
    }

    /** @param array<string, mixed> $attributes */
    private function createMessage(array $attributes): TinNhan
    {
        if (($attributes['ThoiGianGui'] ?? null) instanceof \DateTimeInterface) {
            $attributes['ThoiGianGui'] = $attributes['ThoiGianGui']->format('Y-m-d H:i:s');
        }

        $response = $this->postgrestRequest(
            'POST',
            '/rest/v1/TinNhan?select=TinNhanID,NguoiGuiID,NguoiNhanID,DonHangID,NoiDung,ThoiGianGui,TrangThai',
            [$attributes],
            'return=representation',
        )->throw();
        $created = $response->json();
        if (! is_array($created) || ! isset($created[0]) || ! is_array($created[0])) {
            throw new \RuntimeException('Supabase did not return the created chat message.');
        }

        return TinNhan::query()->getModel()->newFromBuilder($created[0]);
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

        $encrypted = $this->messageCryptoService->encryptContent(trim($content));

        return $this->createMessage([
            'NguoiGuiID' => $sender->getKey(),
            'NguoiNhanID' => $recipientId,
            'DonHangID' => $order->DonHangID,
            'NoiDung' => '',
            'NoiDungMaHoa' => $encrypted['ciphertext'],
            'NoiDungNonce' => $encrypted['nonce'],
            'MaHoaPhienBan' => 1,
            'ThoiGianGui' => now('UTC'),
            'TrangThai' => 'Đã gửi',
        ]);
    }
}
