<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Notification::query();

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['order_id'])) {
            $query->where('order_id', $filters['order_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('type', 'LIKE', "%{$search}%")
                    ->orWhere('message', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($filters['read'])) {
            $filters['read'] === 'unread'
                ? $query->whereNull('read_at')
                : $query->whereNotNull('read_at');
        }

        return $query->with('user', 'order')
            // Thông báo chưa đọc luôn được ưu tiên đẩy lên trên, đã đọc nằm sau
            ->orderByRaw('CASE WHEN read_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('created_at')
            ->paginate(10);
    }

    public function find(int $id): ?Notification
    {
        return Notification::withTrashed()->find($id);
    }

    public function create(array $data): Notification
    {
        if (empty($data['sent_at'])) {
            $data['sent_at'] = now();
        }
        return Notification::create($data);
    }

    public function update(Notification $notification, array $data): Notification
    {
        $notification->update($data);
        return $notification->fresh();
    }

    public function delete(Notification $notification): bool
    {
        return $notification->delete();
    }

    public function markAsRead(int $id): ?Notification
    {
        $notification = Notification::find($id);
        if ($notification) {
            $notification->update(['read_at' => now()]);
        }
        return $notification;
    }

    public function markAllAsRead(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}