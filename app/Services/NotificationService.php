<?php

namespace App\Services;

use App\Models\ThongBao;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationService
{
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

    public function findForDetails(int $id): ?ThongBao
    {
        return ThongBao::with(['taiKhoan', 'donHang'])->find($id);
    }

    public function create(array $data): ThongBao
    {
        if (empty($data['sent_at'])) {
            $data['ThoiGianGui'] = now();
        } else {
            $data['ThoiGianGui'] = $data['sent_at'];
        }

        return ThongBao::create($data);
    }

    public function update(ThongBao $notification, array $data): ThongBao
    {
        $notification->update($data);

        return $notification->fresh();
    }

    public function delete(ThongBao $notification): bool
    {
        return $notification->delete();
    }

    public function markAsRead(int $id): ?ThongBao
    {
        $notification = ThongBao::find($id);
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
