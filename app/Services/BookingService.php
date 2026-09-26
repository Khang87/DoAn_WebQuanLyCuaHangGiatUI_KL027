<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Booking::query();

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (!empty($filters['method'])) {
            $query->where('method', $filters['method']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $numericPart = preg_replace('/[^0-9]/', '', $search);
                if (!empty($numericPart)) {
                    $q->where('id', $numericPart);
                }
                $q->orWhereHas('customer', function ($sub) use ($search) {
                    $sub->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('phone', 'LIKE', "%{$search}%");
                });
                $q->orWhere('address', 'LIKE', "%{$search}%");
            });
        }

        $allowedSorts = ['id', 'customer_id', 'method', 'status', 'scheduled_date', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts) ? $filters['sort_by'] : 'created_at';
        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->with(['customer', 'staff', 'order'])->orderBy($sortBy, $sortOrder)->paginate(10)->withQueryString();
    }

    public function find(int $id): ?Booking
    {
        return Booking::withTrashed()->with(['customer', 'staff', 'order'])->find($id);
    }

    public function create(array $data): Booking
    {
        return Booking::create($data);
    }

    /**
     * Cập nhật đặt lịch và tự động sinh đơn hàng khi trạng thái chuyển sang
     * "Đã xác nhận" (hoặc các trạng thái sau đó).
     *
     * Việc sinh đơn đặt ở tầng service thay vì controller để MỌI đường đi tới
     * trạng thái "đã xác nhận" đều tự động có đơn: nút "Xác nhận", dropdown
     * trạng thái trong form chỉnh sửa, hay seeder/import sau này.
     */
    public function update(Booking $booking, array $data): Booking
    {
        $previousStatus = $booking->status;

        $booking->update($data);

        $booking = $booking->fresh();

        $this->syncOrderOnStatusChange($booking, $previousStatus);

        return $booking;
    }

    /**
     * Nếu lịch hẹn vừa chuyển sang trạng thái "đủ điều kiện có đơn" thì tạo đơn.
     * Trả về đơn vừa tạo, hoặc null nếu không cần tạo.
     */
    public function syncOrderOnStatusChange(Booking $booking, ?string $previousStatus = null): ?Order
    {
        if (! $booking->isConvertibleToOrder()) {
            return null;
        }

        // Đã có đơn rồi thì không tạo lại (idempotent).
        if ($this->hasConvertedOrder($booking)) {
            return null;
        }

        return $this->createOrderFor($booking);
    }

    public function delete(Booking $booking): bool
    {
        return $booking->delete();
    }

    public function restore(int $id): ?Booking
    {
        $booking = Booking::onlyTrashed()->find($id);

        if ($booking) {
            $booking->restore();
        }

        return $booking;
    }

    /**
     * Xác nhận đặt lịch và chuyển thành đơn hàng.
     *
     * Hàm này là idempotent: nếu đặt lịch đã có đơn (orders.booking_id) thì trả
     * lại đơn cũ thay vì tạo thêm, nên bấm "Xác nhận" nhiều lần cũng không sinh
     * đơn trùng. Toàn bộ việc tạo đơn + tạo phiếu giao chạy trong một
     * transaction nên thất bại giữa chừng sẽ rollback trọn vẹn.
     */
    public function confirmAndCreateOrder(Booking $booking): ?Order
    {
        $existing = $this->findOrderForBooking($booking);

        if ($existing) {
            return $existing;
        }

        if ($booking->status !== 'confirmed') {
            return null;
        }

        return DB::transaction(function () use ($booking) {
            $existing = $this->findOrderForBooking($booking, true);

            if ($existing) {
                return $existing;
            }

            return $this->insertOrderAndDelivery($booking)->fresh();
        });
    }

    /**
     * Tạo đơn + phiếu giao cho lịch hẹn, KHÔNG đụng tới trạng thái lịch hẹn.
     * Dùng cho đường tự động hoá theo trạng thái.
     */
    private function createOrderFor(Booking $booking): Order
    {
        return DB::transaction(function () use ($booking) {
            $existing = $this->findOrderForBooking($booking, true);

            if ($existing) {
                return $existing;
            }

            return $this->insertOrderAndDelivery($booking)->fresh();
        });
    }

    /**
     * Insert bản ghi đơn hàng (kèm phiếu giao) có tham chiếu về lịch hẹn.
     * Bản ghi đơn luôn chứa mã tham chiếu của lịch đặt: qua quan hệ
     * `orders.booking_id` và qua mã ghi trong phần ghi chú.
     */
    private function insertOrderAndDelivery(Booking $booking): Order
    {
        $bookingCode = $booking->code ?: Booking::nextCode();

        $order = Order::create([
            'code' => 'DH' . str_pad((string) ((Order::max('id') ?? 0) + 1), 3, '0', STR_PAD_LEFT),
            'customer_id' => $booking->customer_id,
            'employee_id' => $booking->staff_id,
            'booking_id' => $booking->id,
            'status' => 'pending',
            'notes' => $this->buildOrderNotes($booking, $bookingCode),
            'total_amount' => 0,
            'weight_kg' => '',
            'quantity_items' => '',
        ]);

        \App\Models\Delivery::create([
            'code' => 'GH' . str_pad((string) ((\App\Models\Delivery::max('id') ?? 0) + 1), 4, '0', STR_PAD_LEFT),
            'order_id' => $order->id,
            'customer_id' => $booking->customer_id,
            'employee_id' => $booking->staff_id,
            'method' => $booking->method,
            'status' => 'pending',
            'notes' => $booking->notes,
        ]);

        return $order;
    }

    /**
     * Ghi chú của đơn luôn mở đầu bằng mã tham chiếu lịch đặt để nhân viên
     * nhìn bảng đơn là biết đơn này sinh ra từ lịch nào.
     */
    private function buildOrderNotes(Booking $booking, string $bookingCode): string
    {
        $reference = 'Tự động tạo từ đặt lịch ' . $bookingCode
            . ' (' . $booking->method_label . ' ngày '
            . ($booking->scheduled_date?->format('d/m/Y') ?? '—') . ')';

        return $booking->notes
            ? $reference . ' | ' . $booking->notes
            : $reference;
    }

    /**
     * Đặt lịch này đã có đơn hàng được tạo từ trước chưa?
     */
    public function hasConvertedOrder(Booking $booking): bool
    {
        return $this->findOrderForBooking($booking) !== null;
    }

    /**
     * Đơn hàng đã được tạo từ đặt lịch này (nếu có).
     */
    private function findOrderForBooking(Booking $booking, bool $lockForUpdate = false): ?Order
    {
        $query = Order::withTrashed()->where('booking_id', $booking->id);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }
}
