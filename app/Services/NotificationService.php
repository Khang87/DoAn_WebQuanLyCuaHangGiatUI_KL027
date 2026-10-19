<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\TaiKhoan;
use App\Models\ThongBao;
use App\Support\InternalCommunicationPolicy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class NotificationService
{
    public const RECIPIENT_GROUPS = [
        'all' => 'Tất cả người dùng',
        'all_customers' => 'Tất cả khách hàng',
        'all_staff' => 'Tất cả nhân viên',
    ];

    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = ThongBao::query();

        if (! empty($filters['user_id'])) {
            $query->where('TaiKhoanID', $filters['user_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('LoaiThongBao', $filters['type']);
        }

        if (! empty($filters['order_id'])) {
            $query->where('DonHangID', $filters['order_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('LoaiThongBao', 'LIKE', "%{$search}%")
                    ->orWhere('NoiDung', 'LIKE', "%{$search}%");
            });
        }

        if (! empty($filters['read'])) {
            $filters['read'] === 'unread'
                ? $query->where('DaDoc', false)
                : $query->where('DaDoc', true);
        }

        return $query->with(['taiKhoan', 'donHang'])
            // Thông báo chưa đọc luôn được ưu tiên đẩy lên trên, đã đọc nằm sau.
            // `DaDoc` là kiểu boolean nên so sánh với FALSE chứ không phải 0, và cần
            // nháy kép vì raw SQL không qua wrapper của Eloquent.
            ->orderByRaw('CASE WHEN "DaDoc" = FALSE THEN 0 ELSE 1 END')
            ->orderByDesc('ThoiGianGui')
            ->paginate(10);
    }

    public function find(int $id): ?ThongBao
    {
        return ThongBao::find($id);
    }

    public function findForDetails(int $id, ?int $recipientId = null): ?ThongBao
    {
        return ThongBao::query()
            ->when($recipientId !== null, fn ($q) => $q->where('TaiKhoanID', $recipientId))
            ->with(['taiKhoan', 'donHang', 'tinNhan.sender'])
            ->find($id);
    }

    public function create(array $data, ?Booking $booking = null): ThongBao
    {
        if (array_key_exists('BookingID', $data)) {
            throw new InvalidArgumentException('Booking links must be supplied through a persisted Booking model.');
        }
        if ($booking !== null && ! $booking->exists) {
            throw new InvalidArgumentException('A notification can only link to a persisted Booking.');
        }

        $data['ThoiGianGui'] = now();

        $notification = new ThongBao;
        $notification->fill($data);

        if ($booking !== null) {
            $notification->booking()->associate($booking);
        }

        $notification->save();

        return $notification;
    }

    public function createForRecipient(string $recipient, array $data): int
    {
        $query = TaiKhoan::query()->where('TrangThai', 'Hoạt động');

        if ($recipient === 'all_customers') {
            $query->whereHas('vaiTros', fn ($roleQuery) => $roleQuery->where('TenVaiTro', 'Khách hàng'));
        } elseif ($recipient === 'all_staff') {
            InternalCommunicationPolicy::scopeAccounts($query);
        } elseif ($recipient !== 'all') {
            $query->where('TaiKhoanID', (int) $recipient);
        }

        $notification = [
            'DonHangID' => $data['DonHangID'] ?? null,
            'LoaiThongBao' => $data['LoaiThongBao'] ?? null,
            'TieuDe' => $data['TieuDe'],
            'NoiDung' => $data['NoiDung'],
            'ThoiGianGui' => now()->toDateTimeString(),
            'DaDoc' => false,
        ];
        $createdCount = 0;

        DB::transaction(function () use ($query, $notification, &$createdCount): void {
            $query->select('TaiKhoanID')->chunkById(500, function ($accounts) use ($notification, &$createdCount): void {
                $rows = $accounts->map(fn (TaiKhoan $account): array => [
                    'TaiKhoanID' => $account->TaiKhoanID,
                    ...$notification,
                ])->all();

                if ($rows !== []) {
                    DB::table('ThongBao')->insert($rows);
                    $createdCount += count($rows);
                }
            }, 'TaiKhoanID');
        });

        return $createdCount;
    }

    public function notifyManagement(
        string $type,
        string $title,
        string $body,
        ?int $orderId = null,
        ?int $bookingId = null,
        ?string $dedupeReference = null,
    ): int {
        if ($orderId === null && $bookingId === null) {
            throw new InvalidArgumentException('A notification must be associated with an order or booking.');
        }

        if ($bookingId !== null && ($dedupeReference === null || $dedupeReference === '')) {
            throw new InvalidArgumentException('A booking notification requires a deduplication reference.');
        }

        return DB::transaction(function () use ($type, $title, $body, $orderId, $bookingId, $dedupeReference): int {
            $recipientQuery = TaiKhoan::query()->select('TaiKhoan.TaiKhoanID');
            InternalCommunicationPolicy::scopeManagementAccounts($recipientQuery);
            $recipientIds = $recipientQuery
                ->distinct()
                ->pluck('TaiKhoanID');

            if ($recipientIds->isEmpty()) {
                return 0;
            }

            $existingRecipientIds = DB::table('ThongBao')
                ->whereIn('TaiKhoanID', $recipientIds)
                ->where('LoaiThongBao', $type)
                ->where(function ($query) use ($orderId, $bookingId, $dedupeReference): void {
                    if ($orderId !== null) {
                        $query->where('DonHangID', $orderId);
                    }

                    if ($bookingId !== null) {
                        $query->orWhere('BookingID', $bookingId)
                            ->orWhere(function ($bookingQuery) use ($dedupeReference): void {
                                $bookingQuery->whereNull('DonHangID')
                                    ->where('NoiDung', 'LIKE', "%{$dedupeReference}%");
                            });
                    }
                })
                ->pluck('TaiKhoanID')
                ->all();

            $existingRecipientIds = array_fill_keys(array_map('intval', $existingRecipientIds), true);
            $notification = [
                'DonHangID' => $orderId,
                'BookingID' => $bookingId,
                'LoaiThongBao' => $type,
                'TieuDe' => $title,
                'NoiDung' => $body,
                'ThoiGianGui' => now()->toDateTimeString(),
                'DaDoc' => false,
            ];
            $rows = $recipientIds
                ->reject(fn ($recipientId): bool => isset($existingRecipientIds[(int) $recipientId]))
                ->map(fn ($recipientId): array => [
                    'TaiKhoanID' => $recipientId,
                    ...$notification,
                ])
                ->all();

            if ($rows !== []) {
                DB::table('ThongBao')->insert($rows);
            }

            return count($rows);
        });
    }

    public function update(ThongBao $notification, array $data): ThongBao
    {
        if (array_key_exists('BookingID', $data)) {
            throw new InvalidArgumentException('Booking links cannot be changed through notification updates.');
        }

        $notification->update($data);

        return $notification->fresh();
    }

    public function delete(ThongBao $notification): bool
    {
        return $notification->delete();
    }

    public function markAsRead(int $id, int $recipientId): ?ThongBao
    {
        $notification = ThongBao::where('TaiKhoanID', $recipientId)->find($id);
        if ($notification) {
            $notification->update(['DaDoc' => true]);
        }

        return $notification;
    }

    public function markNotificationAsRead(ThongBao $notification): ThongBao
    {
        if (! $notification->DaDoc) {
            $notification->DaDoc = true;
            $notification->save();
        }

        return $notification;
    }

    public function markAllAsRead(int $userId): int
    {
        return ThongBao::where('TaiKhoanID', $userId)
            ->where('DaDoc', false)
            ->update(['DaDoc' => true]);
    }
}
