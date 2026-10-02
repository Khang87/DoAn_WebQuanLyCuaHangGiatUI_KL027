<?php

namespace App\Services;

use App\Enums\BookingMethod;
use App\Enums\BookingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\BangGia;
use App\Models\Booking;
use App\Models\ChiTietBooking;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\GiaoNhan;
use App\Models\NhatKyHeThong;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Booking::query();

        if (! empty($filters['customer_id'])) {
            $query->where('KhachHangID', $filters['customer_id']);
        }

        if (! empty($filters['method'])) {
            $query->where('HinhThucNhanDo', $filters['method']);
        }

        if (! empty($filters['status'])) {
            $query->where('TrangThai', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $numericPart = preg_replace('/[^0-9]/', '', $search);
                if (! empty($numericPart)) {
                    $q->where('BookingID', $numericPart);
                }
                $q->orWhere('MaBooking', 'LIKE', "%{$search}%");
                $q->orWhere('DiaChiNhan', 'LIKE', "%{$search}%");
                $q->orWhereHas('khachHang', function ($sub) use ($search) {
                    $sub->where('HoTen', 'LIKE', "%{$search}%")
                        ->orWhere('SoDienThoai', 'LIKE', "%{$search}%");
                });
            });
        }

        $allowedSorts = ['BookingID', 'MaBooking', 'KhachHangID', 'HinhThucNhanDo', 'TrangThai', 'NgayHen', 'NgayTao'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts) ? $filters['sort_by'] : 'NgayTao';
        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->with(['khachHang', 'nhanVien', 'donHangs'])
            ->orderBy($sortBy, $sortOrder)
            ->paginate(10)
            ->withQueryString();
    }

    public function find(int $id): ?Booking
    {
        return Booking::query()
            ->with([
                'khachHang',
                'chiTietBookings.dichVu',
                'chiTietBookings.loaiDoGiat',
                'chiTietBookings.donViTinh',
                'nhanVien',
                'nhanVienXacNhan',
                'donHangs',
            ])
            ->find($id);
    }

    public function create(array $data): Booking
    {
        return DB::transaction(function () use ($data): Booking {
            $booking = Booking::create($this->mapRequestData($data));

            if (array_key_exists('items', $data)) {
                $this->syncBookingItems($booking, $data['items']);
            }

            $this->recordBookingAudit(
                $booking,
                [],
                $this->bookingAuditSnapshot($booking),
                [],
                $this->bookingItemsAuditSnapshot($booking),
            );

            return $booking->fresh(['chiTietBookings']);
        });
    }

    public function update(Booking $booking, array $data): Booking
    {
        return DB::transaction(function () use ($booking, $data): Booking {
            $lockedBooking = Booking::query()
                ->lockForUpdate()
                ->findOrFail($booking->BookingID);
            $before = $this->bookingAuditSnapshot($lockedBooking);
            $beforeItems = $this->bookingItemsAuditSnapshot($lockedBooking);

            if (
                ($data['status'] ?? null) === BookingStatus::Cancelled->value
                && $lockedBooking->TrangThai !== BookingStatus::Cancelled->value
                && $this->hasUncancelledOrder($lockedBooking)
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Không thể hủy riêng đặt lịch đã có đơn hàng. Hãy xử lý đơn hàng liên kết trước.',
                ]);
            }

            if ($this->hasConvertedOrder($lockedBooking) && array_key_exists('items', $data)) {
                throw ValidationException::withMessages([
                    'items' => 'Không thể sửa các dòng dịch vụ sau khi Booking đã được chuyển thành đơn hàng.',
                ]);
            }

            $mappedData = $this->mapRequestData($data);
            $wasPending = $lockedBooking->TrangThai === BookingStatus::Pending->value;
            $isBeingConfirmed = ($data['status'] ?? null) === BookingStatus::Confirmed->value;

            if ($wasPending && $isBeingConfirmed) {
                $employeeId = auth()->user()?->NhanVienID;

                if (! $employeeId) {
                    throw ValidationException::withMessages([
                        'status' => 'Tài khoản hiện tại chưa liên kết hồ sơ nhân viên để xác nhận Booking.',
                    ]);
                }

                $mappedData['NhanVienXacNhanID'] = $employeeId;
                $mappedData['ThoiGianXacNhan'] = now();
            }

            if (array_key_exists('items', $data)) {
                $this->syncBookingItems($lockedBooking, $data['items']);
            }

            $lockedBooking->update($mappedData);
            $lockedBooking->refresh();
            $after = $this->bookingAuditSnapshot($lockedBooking);
            $afterItems = $this->bookingItemsAuditSnapshot($lockedBooking);

            if (
                $wasPending
                && $isBeingConfirmed
                && ($before !== $after || $beforeItems !== $afterItems)
            ) {
                $this->recordBookingAudit($lockedBooking, $before, $after, $beforeItems, $afterItems);
            }

            if ($lockedBooking->isConvertibleToOrder() && ! $this->hasConvertedOrder($lockedBooking)) {
                $this->insertOrderAndDelivery($lockedBooking);
            }

            return $lockedBooking->fresh([
                'khachHang',
                'chiTietBookings.dichVu',
                'chiTietBookings.loaiDoGiat',
                'chiTietBookings.donViTinh',
                'nhanVienXacNhan',
                'donHangs',
            ]);
        });
    }

    /**
     * Form quản trị gửi tên field tiếng Anh, cột trong bảng lại là tiếng Việt
     * nên phải đổi tên trước khi ghi xuống model.
     */
    private function mapRequestData(array $data): array
    {
        $map = [
            'customer_id' => 'KhachHangID',
            'staff_id' => 'NhanVienID',
            'method' => 'HinhThucNhanDo',
            'scheduled_date' => 'NgayHen',
            'scheduled_time' => 'GioHen',
            'address' => 'DiaChiNhan',
            'notes' => 'GhiChu',
            'status' => 'TrangThai',
        ];

        $mapped = [];

        foreach ($map as $key => $column) {
            if (array_key_exists($key, $data)) {
                $mapped[$column] = $data[$key];
            }
        }

        return $mapped;
    }

    public function delete(Booking $booking): bool
    {
        return DB::transaction(function () use ($booking): bool {
            $lockedBooking = Booking::query()
                ->lockForUpdate()
                ->findOrFail($booking->BookingID);

            if ($this->hasConvertedOrder($lockedBooking)) {
                return false;
            }

            return (bool) $lockedBooking->delete();
        });
    }

    /**
     * Xác nhận đặt lịch và chuyển thành đơn hàng.
     *
     * Hàm này là idempotent: nếu đặt lịch đã có đơn (orders.booking_id) thì trả
     * lại đơn cũ thay vì tạo thêm, nên bấm "Xác nhận" nhiều lần cũng không sinh
     * đơn trùng. Toàn bộ việc tạo đơn + tạo phiếu giao chạy trong một
     * transaction nên thất bại giữa chừng sẽ rollback trọn vẹn.
     */
    public function confirmAndCreateOrder(Booking $booking): ?DonHang
    {
        $existing = $this->findOrderForBooking($booking);

        if ($existing) {
            return $existing;
        }

        if (! $booking->isConvertibleToOrder()) {
            return null;
        }

        return DB::transaction(function () use ($booking): DonHang {
            $lockedBooking = Booking::query()
                ->lockForUpdate()
                ->findOrFail($booking->BookingID);
            $existing = $this->findOrderForBooking($lockedBooking);

            if ($existing) {
                return $existing;
            }

            if (! $lockedBooking->isConvertibleToOrder()) {
                throw ValidationException::withMessages([
                    'status' => 'Chỉ đặt lịch đã xác nhận mới có thể tạo đơn hàng.',
                ]);
            }

            if ($lockedBooking->NhanVienXacNhanID === null || $lockedBooking->ThoiGianXacNhan === null) {
                $before = $this->bookingAuditSnapshot($lockedBooking);
                $beforeItems = $this->bookingItemsAuditSnapshot($lockedBooking);
                $employeeId = auth()->user()?->NhanVienID;

                if (! $employeeId) {
                    throw ValidationException::withMessages([
                        'status' => 'Tài khoản hiện tại chưa liên kết hồ sơ nhân viên để ghi nhận xác nhận Booking.',
                    ]);
                }

                $lockedBooking->update([
                    'NhanVienXacNhanID' => $lockedBooking->NhanVienXacNhanID ?? $employeeId,
                    'ThoiGianXacNhan' => $lockedBooking->ThoiGianXacNhan ?? now(),
                ]);
                $lockedBooking->refresh();
                $this->recordBookingAudit(
                    $lockedBooking,
                    $before,
                    $this->bookingAuditSnapshot($lockedBooking),
                    $beforeItems,
                    $this->bookingItemsAuditSnapshot($lockedBooking),
                );
            }

            return $this->insertOrderAndDelivery($lockedBooking)->fresh();
        });
    }

    /**
     * Insert bản ghi đơn hàng (kèm phiếu giao) có tham chiếu về lịch hẹn.
     * Bản ghi đơn luôn chứa mã tham chiếu của lịch đặt: qua quan hệ
     * `orders.booking_id` và qua mã ghi trong phần ghi chú.
     */
    private function insertOrderAndDelivery(Booking $booking): DonHang
    {
        $snapshots = $this->serviceSnapshotsForOrder($booking);
        $bookingCode = $booking->MaBooking ?: Booking::nextCode();
        $total = array_sum(array_column($snapshots, 'ThanhTien'));

        $order = DonHang::create([
            'MaDonHang' => 'TMP'.Str::ulid(),
            'KhachHangID' => $booking->KhachHangID,
            'NhanVienID' => $booking->NhanVienID,
            'BookingID' => $booking->BookingID,
            'TrangThai' => OrderStatus::Pending->value,
            'GhiChu' => $this->buildOrderNotes($booking, $bookingCode),
            'TongTien' => $total,
            'ThanhTien' => $total,
        ]);
        $order->update([
            'MaDonHang' => 'DH'.str_pad((string) $order->DonHangID, 3, '0', STR_PAD_LEFT),
        ]);

        foreach ($snapshots as $snapshot) {
            ChiTietDonHang::create(array_merge(
                ['DonHangID' => $order->DonHangID],
                $snapshot,
            ));
        }

        GiaoNhan::create([
            'DonHangID' => $order->DonHangID,
            'NhanVienID' => $booking->NhanVienID,
            'HinhThuc' => $booking->HinhThucNhanDo,
            'LoaiGiaoNhan' => $booking->methodEnum()->deliveryType(),
            'DiaChi' => $booking->methodEnum() === BookingMethod::GiaoDo
                ? $booking->DiaChiNhan
                : null,
            'ThoiGianDuKien' => $booking->NgayHen->format('Y-m-d').' '.$booking->GioHen->format('H:i:s'),
            'TrangThai' => DeliveryStatus::Pending->dbValue(),
            'GhiChu' => $booking->GhiChu,
        ]);

        return $order;
    }

    /**
     * Ghi chú của đơn luôn mở đầu bằng mã tham chiếu lịch đặt để nhân viên
     * nhìn bảng đơn là biết đơn này sinh ra từ lịch nào.
     */
    private function buildOrderNotes(Booking $booking, string $bookingCode): string
    {
        $reference = 'Tự động tạo từ đặt lịch '.$bookingCode
            .' ('.$booking->method_label.' ngày '
            .($booking->NgayHen?->format('d/m/Y') ?? '—').')';

        return mb_substr($booking->GhiChu
            ? $reference.' | '.$booking->GhiChu
            : $reference, 0, 500);
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
    private function findOrderForBooking(Booking $booking): ?DonHang
    {
        return DonHang::where('BookingID', $booking->BookingID)->first();
    }

    private function hasUncancelledOrder(Booking $booking): bool
    {
        return DonHang::query()
            ->where('BookingID', $booking->BookingID)
            ->where('TrangThai', '!=', OrderStatus::Cancelled->value)
            ->exists();
    }

    /**
     * Reprice Booking item inputs using the exact service/garment/unit tuple.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncBookingItems(Booking $booking, array $items): void
    {
        $rows = [];

        foreach ($items as $index => $item) {
            $serviceId = (int) ($item['DichVuID'] ?? 0);
            $garmentId = (int) ($item['LoaiDoGiatID'] ?? 0);
            $unitId = (int) ($item['DonViTinhID'] ?? 0);
            $quantity = ($item['SoLuong'] ?? '') !== '' ? (float) $item['SoLuong'] : null;
            $weight = ($item['KhoiLuong'] ?? '') !== '' ? (float) $item['KhoiLuong'] : null;
            $pricing = BangGia::getLatestPricing($serviceId, $garmentId, $unitId);

            if (! $pricing) {
                throw ValidationException::withMessages([
                    "items.{$index}.DonViTinhID" => 'Không có bảng giá đang hiệu lực cho tổ hợp dịch vụ, loại đồ và đơn vị tính đã chọn.',
                ]);
            }

            $isWeightUnit = BangGia::isWeightUnit($pricing->unit);
            if (
                ($isWeightUnit && ($weight === null || $weight <= 0 || $quantity !== null))
                || (! $isWeightUnit && ($quantity === null || $quantity <= 0 || $weight !== null))
            ) {
                throw ValidationException::withMessages([
                    "items.{$index}.SoLuong" => 'Chỉ nhập khối lượng cho đơn vị KG; các đơn vị khác phải nhập số lượng.',
                ]);
            }

            $billableAmount = $isWeightUnit
                ? max($weight, (float) config('giatui.khoi_luong_toi_thieu', 3.0))
                : $quantity;

            $rows[] = [
                'DichVuID' => $serviceId,
                'LoaiDoGiatID' => $garmentId,
                'DonViTinhID' => $unitId,
                'SoLuong' => $quantity,
                'KhoiLuong' => $weight,
                'DonGia' => (float) $pricing->DonGia,
                'ThanhTien' => round((float) $pricing->DonGia * $billableAmount, 2),
                'GhiChu' => $item['GhiChu'] ?? null,
            ];
        }

        $booking->chiTietBookings()->delete();
        foreach ($rows as $row) {
            $booking->chiTietBookings()->create($row);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serviceSnapshotsForOrder(Booking $booking): array
    {
        $items = $booking->chiTietBookings()
            ->with('donViTinh')
            ->orderBy('ChiTietBookingID')
            ->get();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Đặt lịch chưa có dòng dịch vụ. Hãy bổ sung ít nhất một dòng trước khi xác nhận.',
            ]);
        }

        return $items->map(function (ChiTietBooking $item): array {
            $quantity = $item->SoLuong !== null ? (float) $item->SoLuong : null;
            $weight = $item->KhoiLuong !== null ? (float) $item->KhoiLuong : null;
            $unit = $item->donViTinh;

            if (! $unit) {
                throw ValidationException::withMessages([
                    'items' => 'Một hoặc nhiều dòng dịch vụ tham chiếu đến đơn vị tính không tồn tại.',
                ]);
            }

            $isWeightUnit = $unit->isWeightUnit();

            if (
                $item->DichVuID === null
                || $item->LoaiDoGiatID === null
                || $item->DonViTinhID === null
                || (($quantity === null) === ($weight === null))
                || ($quantity !== null && $quantity <= 0)
                || ($weight !== null && $weight <= 0)
                || ($isWeightUnit && ($quantity !== null || $weight === null))
                || (! $isWeightUnit && ($quantity === null || $weight !== null))
                || (float) $item->DonGia < 0
                || (float) $item->ThanhTien < 0
            ) {
                throw ValidationException::withMessages([
                    'items' => 'Một hoặc nhiều dòng dịch vụ trong Booking không hợp lệ.',
                ]);
            }

            return [
                'DichVuID' => $item->DichVuID,
                'LoaiDoGiatID' => $item->LoaiDoGiatID,
                'DonViTinhID' => $item->DonViTinhID,
                'SoLuong' => $quantity,
                'KhoiLuong' => $weight,
                'DonGia' => (float) $item->DonGia,
                'ThanhTien' => (float) $item->ThanhTien,
                'GhiChu' => $item->GhiChu,
            ];
        })->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingAuditSnapshot(Booking $booking): array
    {
        return $booking->only([
            'MaBooking',
            'KhachHangID',
            'HinhThucNhanDo',
            'DiaChiNhan',
            'NgayHen',
            'GioHen',
            'GhiChu',
            'TrangThai',
            'NhanVienID',
            'NhanVienXacNhanID',
            'ThoiGianXacNhan',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bookingItemsAuditSnapshot(Booking $booking): array
    {
        return $booking->chiTietBookings()
            ->orderBy('ChiTietBookingID')
            ->get([
                'ChiTietBookingID',
                'DichVuID',
                'LoaiDoGiatID',
                'DonViTinhID',
                'SoLuong',
                'KhoiLuong',
                'DonGia',
                'ThanhTien',
                'GhiChu',
            ])
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<int, array<string, mixed>>  $beforeItems
     * @param  array<int, array<string, mixed>>  $afterItems
     */
    private function recordBookingAudit(
        Booking $booking,
        array $before,
        array $after,
        array $beforeItems,
        array $afterItems,
    ): void {
        $action = $before === []
            ? 'Tạo Booking'
            : (($after['TrangThai'] ?? null) === BookingStatus::Confirmed->value
                ? 'Xác nhận Booking'
                : 'Cập nhật Booking');

        NhatKyHeThong::create([
            'TaiKhoanID' => auth()->id(),
            'HanhDong' => $action,
            'BangDuLieu' => 'Booking',
            'BanGhiID' => $booking->BookingID,
            'DuLieuCu' => ['Booking' => $before, 'ChiTietBooking' => $beforeItems],
            'DuLieuMoi' => ['Booking' => $after, 'ChiTietBooking' => $afterItems],
            'LyDo' => null,
            'ThoiGian' => now(),
            'IPAddress' => request()->ip(),
            'UserAgent' => request()->userAgent(),
        ]);
    }
}
