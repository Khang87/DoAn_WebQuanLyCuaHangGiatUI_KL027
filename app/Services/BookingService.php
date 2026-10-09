<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Models\BangGia;
use App\Models\Booking;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\KhuyenMai;
use App\Models\NhatKyHeThong;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(
        private OrderService $orderService,
        private PricingService $pricingService,
    ) {}

    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Booking::query();

        if (! empty($filters['customer_id'])) {
            $query->where('KhachHangID', $filters['customer_id']);
        }

        if (! empty($filters['method'])) {
            $query->where('HinhThucNhanDo', $filters['method']);
        }

        if (! empty($filters['return_method'])) {
            $query->where('HinhThucTraDo', $filters['return_method']);
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
                $q->orWhere('DiaChiNhan', 'LIKE', "%{$search}%")
                    ->orWhere('DiaChiTra', 'LIKE', "%{$search}%");
                $q->orWhereHas('khachHang', function ($sub) use ($search) {
                    $sub->where('HoTen', 'LIKE', "%{$search}%")
                        ->orWhere('SoDienThoai', 'LIKE', "%{$search}%");
                });
            });
        }

        $allowedSorts = ['BookingID', 'MaBooking', 'KhachHangID', 'HinhThucNhanDo', 'HinhThucTraDo', 'TrangThai', 'NgayHen', 'NgayTao'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts) ? $filters['sort_by'] : 'NgayTao';
        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->with(['khachHang.diemTichLuy', 'nhanVien', 'donHangs', 'chiTietBookings'])
            ->orderBy($sortBy, $sortOrder)
            ->paginate(10)
            ->withQueryString();
    }

    public function find(int $id): ?Booking
    {
        return Booking::query()
            ->with([
                'khachHang.diemTichLuy',
                'khachHang.taiKhoan',
                'chiTietBookings.dichVu',
                'chiTietBookings.loaiDoGiat',
                'chiTietBookings.donViTinh',
                'nhanVien',
                'nhanVienXacNhan',
                'donHangs',
                'khuyenMai',
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
            $hasConvertedOrder = $this->hasConvertedOrder($lockedBooking);

            if (
                $hasConvertedOrder
                && array_key_exists('staff_id', $data)
                && (int) $data['staff_id'] !== (int) $lockedBooking->NhanVienID
            ) {
                throw ValidationException::withMessages([
                    'staff_id' => 'Không thể thay đổi nhân viên phụ trách sau khi Booking đã được chuyển thành đơn hàng.',
                ]);
            }

            if (
                ($data['status'] ?? null) === BookingStatus::Cancelled->value
                && $lockedBooking->TrangThai !== BookingStatus::Cancelled->value
                && $this->hasUncancelledOrder($lockedBooking)
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Không thể hủy riêng đặt lịch đã có đơn hàng. Hãy xử lý đơn hàng liên kết trước.',
                ]);
            }

            if ($hasConvertedOrder && array_key_exists('items', $data)) {
                throw ValidationException::withMessages([
                    'items' => 'Không thể sửa các dòng dịch vụ sau khi Booking đã được chuyển thành đơn hàng.',
                ]);
            }

            unset($data['customer_id']);
            $mappedData = $this->mapRequestData($data);
            $wasPending = $lockedBooking->TrangThai === BookingStatus::Pending->value;
            $isBeingConfirmed = ($data['status'] ?? null) === BookingStatus::Confirmed->value;

            if ($wasPending && $isBeingConfirmed) {
                throw ValidationException::withMessages([
                    'status' => 'Hãy dùng thao tác “Tiếp nhận & tạo đơn” để xác nhận Booking và tạo đơn hàng.',
                ]);
            }

            if (array_key_exists('items', $data)) {
                $this->syncBookingItems($lockedBooking, $data['items']);
            }

            if (($mappedData['TrangThai'] ?? null) === BookingStatus::Cancelled->value) {
                $this->releaseLegacyPoints($lockedBooking);
            }
            $lockedBooking->updateQuietly($mappedData);
            $lockedBooking->refresh();
            $after = $this->bookingAuditSnapshot($lockedBooking);
            $afterItems = $this->bookingItemsAuditSnapshot($lockedBooking);

            if ($before !== $after || $beforeItems !== $afterItems) {
                $this->recordBookingAudit($lockedBooking, $before, $after, $beforeItems, $afterItems);
            }

            return $lockedBooking->fresh([
                'khachHang.diemTichLuy',
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
            'return_method' => 'HinhThucTraDo',
            'return_address' => 'DiaChiTra',
            'notes' => 'GhiChu',
            'status' => 'TrangThai',
        ];

        $mapped = [];

        foreach ($map as $key => $column) {
            if (array_key_exists($key, $data)) {
                $mapped[$column] = $data[$key];
            }
        }

        foreach (['HinhThucNhanDo' => 'DiaChiNhan', 'HinhThucTraDo' => 'DiaChiTra'] as $method => $address) {
            if (($mapped[$method] ?? null) === 'Tại cửa hàng') {
                $mapped[$address] = null;
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

            $this->releaseLegacyPoints($lockedBooking);

            return (bool) $lockedBooking->delete();
        });
    }

    /** Kiểm tra thực tế và tạo đơn trong một giao dịch, có khóa chống tạo trùng. */
    public function inspectBookingAndCreateOrder(
        Booking $booking,
        int $employeeId,
        array $items,
        int $pointsUsed = 0,
        bool $useAllAvailablePoints = false,
        array $bookingData = [],
    ): DonHang {
        return DB::transaction(function () use ($booking, $employeeId, $items, $pointsUsed, $useAllAvailablePoints, $bookingData): DonHang {
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->BookingID);
            $existing = $this->findOrderForBooking($lockedBooking);
            if ($existing !== null) {
                return $existing;
            }

            if (! $lockedBooking->isConvertibleToOrder()) {
                throw ValidationException::withMessages([
                    'booking' => 'Chỉ đặt lịch đang ở trạng thái Chờ xác nhận mới có thể được kiểm tra và tạo đơn.',
                ]);
            }

            $confirmerId = auth()->user()?->NhanVienID;
            if (! $confirmerId) {
                throw ValidationException::withMessages([
                    'booking' => 'Tài khoản hiện tại chưa liên kết hồ sơ nhân viên để xác nhận Booking.',
                ]);
            }

            $before = $this->bookingAuditSnapshot($lockedBooking);
            $beforeItems = $this->bookingItemsAuditSnapshot($lockedBooking);
            unset($bookingData['customer_id'], $bookingData['status'], $bookingData['staff_id']);
            $lockedBooking->fill($this->mapRequestData($bookingData));

            if ($useAllAvailablePoints) {
                $pointsUsed = (int) (KhachHang::query()->find($lockedBooking->KhachHangID)?->points() ?? 0)
                    + ($lockedBooking->getAttribute('DiemDaTru') ? (int) $lockedBooking->getAttribute('DiemSuDung') : 0);
            }

            $order = $this->orderService->createFromBooking($lockedBooking, $items, $employeeId, $pointsUsed);
            $lockedBooking->fill([
                'NhanVienID' => $employeeId,
                'TrangThai' => BookingStatus::Confirmed->value,
                'NhanVienXacNhanID' => $confirmerId,
                'ThoiGianXacNhan' => now(),
            ])->saveQuietly();
            $this->recordBookingAudit(
                $lockedBooking, $before, $this->bookingAuditSnapshot($lockedBooking),
                $beforeItems, $this->bookingItemsAuditSnapshot($lockedBooking),
            );

            return $order->fresh();
        });
    }

    private function releaseLegacyPoints(Booking $booking): void
    {
        if ($booking->getAttribute('DiemDaTru')) {
            KhachHang::find($booking->KhachHangID)?->addPoints((int) $booking->getAttribute('DiemSuDung'));
            $booking->forceFill(['DiemDaTru' => false, 'DiemSuDung' => 0, 'TienGiamDoDiem' => 0])->saveQuietly();
        }
        if ($booking->getAttribute('KhuyenMaiDaTru') && $booking->getAttribute('KhuyenMaiID')) {
            KhuyenMai::whereKey($booking->getAttribute('KhuyenMaiID'))->whereNotNull('SoLuongSuDung')->increment('SoLuongSuDung');
            $booking->forceFill(['KhuyenMaiDaTru' => false])->saveQuietly();
        }
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
            $weight = ($item['KhoiLuong'] ?? '') !== '' ? round((float) $item['KhoiLuong'], 2) : null;
            $pricing = $this->pricingService->getLatestPricing($serviceId, $garmentId, $unitId);

            if (! $pricing) {
                throw ValidationException::withMessages([
                    "items.{$index}.DonViTinhID" => 'Không có bảng giá đang hiệu lực cho tổ hợp dịch vụ, loại đồ và đơn vị tính đã chọn.',
                ]);
            }

            $isWeightUnit = BangGia::isWeightUnit($pricing->unit);
            if (
                ($isWeightUnit && ($weight === null || $weight <= 0 || $quantity !== null))
                || (! $isWeightUnit && (
                    $quantity === null
                    || $quantity < 1
                    || floor($quantity) !== $quantity
                    || ($weight !== null && $weight !== 0.0)
                ))
            ) {
                throw ValidationException::withMessages([
                    "items.{$index}.SoLuong" => $isWeightUnit
                        ? 'Chỉ nhập khối lượng lớn hơn 0 cho đơn vị KG; không thể lưu số lượng món cùng dòng theo schema hiện tại.'
                        : 'Đơn vị tính theo món cần số lượng nguyên dương; khối lượng phải bằng 0 hoặc để trống.',
                ]);
            }

            $rows[] = [
                'DichVuID' => $serviceId,
                'LoaiDoGiatID' => $garmentId,
                'DonViTinhID' => $unitId,
                'SoLuong' => $isWeightUnit ? null : $quantity,
                'KhoiLuong' => $isWeightUnit ? $weight : null,
                'DonGia' => (float) $pricing->DonGia,
                'ThanhTien' => app(TinhTienGiatUiService::class)->tinhThanhTienChiTiet(['DonGia' => $pricing->DonGia, 'KyHieu' => $pricing->unit, 'SoLuong' => $quantity, 'KhoiLuong' => $weight]),
                'GhiChu' => $item['GhiChu'] ?? null,
            ];
        }

        $booking->chiTietBookings()->delete();
        foreach ($rows as $row) {
            $booking->chiTietBookings()->create($row);
        }
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
            'HinhThucTraDo',
            'DiaChiTra',
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
            : (($before['TrangThai'] ?? null) !== BookingStatus::Confirmed->value && ($after['TrangThai'] ?? null) === BookingStatus::Confirmed->value
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
