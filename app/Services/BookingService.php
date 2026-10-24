<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Models\BangGia;
use App\Models\Booking;
use App\Models\BookingGiaoNhan;
use App\Models\BookingKiemKe;
use App\Models\StaffBookingDraft;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\KhuyenMai;
use App\Models\NhanVien;
use App\Models\NhatKyHeThong;
use App\Services\DeliveryFeeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(
        private OrderService $orderService,
        private PricingService $pricingService,
        private NotificationService $notificationService,
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
            $query->whereRaw('COALESCE("HinhThucTraDo", "HinhThucGiaoDo") = ?', [$filters['return_method']]);
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
                    ->orWhereRaw('COALESCE("DiaChiTra", "DiaChiGiao") LIKE ?', ["%{$search}%"]);
                $q->orWhereHas('khachHang', function ($sub) use ($search) {
                    $sub->where('HoTen', 'LIKE', "%{$search}%")
                        ->orWhere('SoDienThoai', 'LIKE', "%{$search}%");
                });
            });
        }

        $allowedSorts = ['BookingID', 'MaBooking', 'KhachHangID', 'HinhThucNhanDo', 'HinhThucTraDo', 'TrangThai', 'NgayHen', 'NgayTao'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts) ? $filters['sort_by'] : 'NgayTao';
        $sortOrder = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'HinhThucTraDo') {
            $query->orderByRaw('COALESCE("HinhThucTraDo", "HinhThucGiaoDo") '.$sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        return $query->with(['khachHang.diemTichLuy', 'nhanVien', 'donHangs', 'chiTietBookings'])
            ->paginate(10)
            ->withQueryString();
    }

    public function find(int $id): ?Booking
    {
        return Booking::query()
            ->with(array_merge([
                'khachHang.diemTichLuy',
                'khachHang.taiKhoan',
                'chiTietBookings.dichVu',
                'chiTietBookings.loaiDoGiat',
                'chiTietBookings.donViTinh',
                'requestedServices.dichVu',
                'requestedServices.loaiDoGiat',
                'requestedServices.donViTinh',
                'nhanVien',
                'nhanVienXacNhan',
                'donHangs',
                'khuyenMai',
            ], [
                'giaoNhanDuKiens', 'kiemKe',
                'giaoNhanDuKiens.nhanVien',
                'donHangs.chiTietDonHangs.dichVu',
                'donHangs.chiTietDonHangs.loaiDoGiat',
                'donHangs.chiTietDonHangs.donViTinh',
            ]))
            ->find($id);
    }

    public function create(array $data): Booking
    {
        return DB::transaction(function () use ($data): Booking {
            CatalogAvailability::assertItemsAvailable($data['items'] ?? []);
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

            $customerName = $booking->khachHang?->HoTen ?? 'Khách hàng';
            $bookingCode = $booking->MaBooking ?: '#'.$booking->BookingID;
            $this->notificationService->notifyManagement(
                'new_booking',
                'Có yêu cầu đặt giặt mới',
                "Khách hàng {$customerName} vừa gửi yêu cầu {$bookingCode}.",
                bookingId: (int) $booking->BookingID,
                dedupeReference: $bookingCode,
            );

            return $booking->fresh(['chiTietBookings']);
        });
    }

    public function approve(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking): Booking {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->BookingID);
            if ($this->hasConvertedOrder($locked)) {
                throw ValidationException::withMessages(['booking' => 'Booking đã có đơn hàng và không cần xác nhận lại.']);
            }
            if (! in_array($locked->TrangThai, [BookingStatus::Pending->value, BookingStatus::Confirmed->value], true)) {
                throw ValidationException::withMessages(['booking' => 'Chỉ có thể xác nhận Booking đang chờ nhận đồ.']);
            }

            $employeeId = $this->requireAuthenticatedEmployeeId('booking', 'Tài khoản cần hồ sơ nhân viên đang hoạt động để xác nhận Booking.');
            $before = $this->bookingAuditSnapshot($locked);
            $beforeItems = $this->bookingItemsAuditSnapshot($locked);

            foreach ([
                ['NHAN_DO', $locked->HinhThucNhanDo, $locked->DiaChiNhan, $locked->PickupDeliveryFee],
                ['GIAO_DO', $locked->HinhThucTraDo, $locked->DiaChiTra, $locked->DeliveryFee],
            ] as [$leg, $method, $address, $fee]) {
                BookingGiaoNhan::query()->firstOrCreate(
                    ['BookingID' => $locked->BookingID, 'LoaiGiaoNhan' => $leg],
                    [
                        'HinhThuc' => $method,
                        'DiaChi' => $method === 'Tại nhà' ? $address : null,
                        'ThoiGianDuKien' => $leg === 'NHAN_DO' ? $locked->NgayHen->format('Y-m-d').' '.$locked->GioHen->format('H:i:s') : null,
                        'PhiGiaoNhan' => $method === 'Tại nhà' ? $fee : 0,
                        'NgayTao' => now(),
                    ],
                );
            }

            if ($locked->TrangThai === BookingStatus::Pending->value) {
                $locked->forceFill([
                    'TrangThai' => BookingStatus::Confirmed->value,
                    'NhanVienXacNhanID' => $employeeId,
                    'ThoiGianXacNhan' => now(),
                    'NgayCapNhat' => now(),
                ])->saveQuietly();
                $this->recordBookingAudit($locked, $before, $this->bookingAuditSnapshot($locked), $beforeItems, $beforeItems);
            }

            return $locked->fresh(['giaoNhanDuKiens', 'kiemKe', 'donHangs']);
        });
    }

    public function cancelPending(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking): Booking {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->BookingID);
            if ($locked->TrangThai !== BookingStatus::Pending->value || $this->hasConvertedOrder($locked)) {
                throw ValidationException::withMessages(['booking' => 'Chỉ có thể hủy Booking đang chờ và chưa có đơn hàng.']);
            }

            return $this->update($locked, ['status' => BookingStatus::Cancelled->value]);
        });
    }

    public function markReceived(Booking $booking): BookingGiaoNhan
    {
        return DB::transaction(function () use ($booking): BookingGiaoNhan {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->BookingID);
            if ($locked->TrangThai !== BookingStatus::Confirmed->value || $this->hasConvertedOrder($locked)) {
                throw ValidationException::withMessages(['booking' => 'Chỉ Booking đã xác nhận và chưa có đơn mới được bắt đầu kiểm kê.']);
            }
            $employeeId = $this->requireAuthenticatedEmployeeId('booking', 'Cần nhân viên đang hoạt động để nhận đồ.');
            $leg = BookingGiaoNhan::query()->where('BookingID', $locked->BookingID)
                ->where('LoaiGiaoNhan', 'NHAN_DO')->lockForUpdate()->firstOrFail();
            if ($leg->TrangThai === DeliveryStatus::Cancelled->dbValue()) {
                throw ValidationException::withMessages(['booking' => 'Không thể bắt đầu kiểm kê trên phiếu đã hủy.']);
            }
            if ($leg->NhanVienID === null || (int) $leg->NhanVienID !== $employeeId) {
                throw ValidationException::withMessages(['booking' => 'Chỉ nhân viên được phân công mới có thể bắt đầu kiểm kê.']);
            }
            if ($leg->TrangThai === DeliveryStatus::Pending->dbValue()) {
                $leg->update([
                    'TrangThai' => DeliveryStatus::Picking->dbValue(),
                    'ThoiGianThucTe' => null,
                ]);
            }

            return $leg->fresh();
        });
    }

    public function submitInspection(Booking $booking, array $data): DonHang
    {
        return DB::transaction(function () use ($booking, $data): DonHang {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->BookingID);
            $existing = $this->findOrderForBooking($locked);
            if ($existing) {
                return $existing;
            }
            if (! in_array($data['customer_accepted'] ?? null, [true, 1, '1', 'yes', 'on'], true)) {
                throw ValidationException::withMessages([
                    'customer_accepted' => 'Cần xác nhận khách hàng đã đồng ý với kết quả kiểm kê và tổng tiền thực tế.',
                ]);
            }
            if ($locked->TrangThai !== BookingStatus::Confirmed->value) {
                throw ValidationException::withMessages(['booking' => 'Hãy xác nhận Booking trước khi kiểm kê và tạo đơn.']);
            }
            $employeeId = $this->requireAuthenticatedEmployeeId(
                'booking',
                'Tài khoản cần hồ sơ nhân viên đang hoạt động để kiểm kê.',
            );
            $intake = BookingGiaoNhan::query()
                ->where('BookingID', $locked->BookingID)
                ->where('LoaiGiaoNhan', 'NHAN_DO')
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertAssignedIntakeHandler($intake, $employeeId);
            if (
                ! in_array($intake->TrangThai, [
                    DeliveryStatus::Pending->dbValue(),
                    DeliveryStatus::Picking->dbValue(),
                ], true)
                || $intake->ThoiGianThucTe !== null
                || $intake->DonHangID !== null
                || $intake->BookingKiemKeID !== null
                || BookingKiemKe::query()->where('BookingID', $locked->BookingID)->exists()
            ) {
                throw ValidationException::withMessages([
                    'booking' => 'Phiếu nhận không còn ở trạng thái cho phép kiểm kê và chốt đơn.',
                ]);
            }

            $data = $this->useOriginalBookingDetails($locked, $data);
            $draft = StaffBookingDraft::query()->whereKey($locked->BookingID)->lockForUpdate()->first();
            if ($draft) {
                $data = $draft->Payload;
                $data['customer_accepted'] = true;
            }
            CatalogAvailability::assertItemsAvailable($data['items'], $locked);
            $promotionId = array_key_exists('promotion_id', $data)
                ? ($data['promotion_id'] === null ? null : (int) $data['promotion_id'])
                : ($locked->KhuyenMaiID === null ? null : (int) $locked->KhuyenMaiID);
            $pointsUsed = (int) ($data['points_used'] ?? $locked->DiemSuDung ?? 0);
            $estimate = $this->orderService->estimateBookingWithRewards($locked, $data['items'], $promotionId, $pointsUsed, true);
            if ($promotionId !== null && $estimate['promotion_warning'] !== null) {
                throw ValidationException::withMessages(['promotion_id' => $estimate['promotion_warning']]);
            }
            if ($pointsUsed > (int) $estimate['available_points']) {
                throw ValidationException::withMessages(['points_used' => 'Số dư điểm đã thay đổi. Vui lòng tính lại giá.']);
            }
            $previousPromotionId = $locked->KhuyenMaiID === null ? null : (int) $locked->KhuyenMaiID;
            $previousPromotionReserved = (bool) $locked->KhuyenMaiDaTru;
            if ($previousPromotionId !== $promotionId && $previousPromotionReserved) {
                KhuyenMai::whereKey($previousPromotionId)->whereNotNull('SoLuongSuDung')->increment('SoLuongSuDung');
            }
            $promotionReserved = $previousPromotionId === $promotionId && $previousPromotionReserved;
            if ($promotionId !== null && ! $promotionReserved) {
                $promotion = KhuyenMai::query()->whereKey($promotionId)->lockForUpdate()->first();
                if (! $promotion || ! $promotion->isValid()
                    || Booking::query()->where('KhachHangID', $locked->KhachHangID)
                        ->where('KhuyenMaiID', $promotionId)->where('TrangThai', '<>', BookingStatus::Cancelled->value)
                        ->where('BookingID', '<>', $locked->BookingID)->exists()) {
                    throw ValidationException::withMessages(['promotion_id' => 'Mã giảm giá vừa hết hạn hoặc đã được dùng cho lịch đặt khác.']);
                }
                if ($promotion->SoLuongSuDung !== null) {
                    $affected = KhuyenMai::query()->whereKey($promotionId)->where('SoLuongSuDung', '>', 0)
                        ->decrement('SoLuongSuDung');
                    if ($affected !== 1) {
                        throw ValidationException::withMessages(['promotion_id' => 'Mã giảm giá vừa hết lượt sử dụng.']);
                    }
                    $promotionReserved = true;
                }
            }
            if ($locked->DiemDaTru && (int) $locked->DiemSuDung > 0) {
                KhachHang::find($locked->KhachHangID)?->addPoints((int) $locked->DiemSuDung);
            }
            $locked->forceFill([
                'KhuyenMaiID' => $promotionId,
                'KhuyenMaiDaTru' => $promotionReserved,
                'DiemSuDung' => $pointsUsed,
                'DiemDaTru' => false,
            ])->saveQuietly();
            $data['items'] = $this->preserveBookingItemNotes($locked, $data['items']);
            $snapshot = $this->inspectionSnapshot($data);
            $changed = $draft?->CoChenhLech ?? $this->inspectionDiffersFromBooking($locked, $snapshot);
            $completedAt = now();
            $inspection = BookingKiemKe::query()->create([
                'BookingID' => $locked->BookingID,
                'DuLieuThucTe' => $snapshot,
                'CoChenhLech' => $changed,
                'PhienBan' => 1,
                'CapNhatLuc' => $completedAt,
            ]);
            $intake->update([
                'TrangThai' => DeliveryStatus::Completed->dbValue(),
                'ThoiGianThucTe' => $completedAt,
                'BookingKiemKeID' => $inspection->BookingKiemKeID,
            ]);

            $order = $this->inspectBookingAndCreateOrder(
                $locked, null, $data['items'], $pointsUsed, false, $data, false, false, true,
            );

            $decision = [
                'customer_accepted' => true,
                'agreed_total' => (float) $order->fresh()->ThanhTien,
                'employee_id' => $employeeId,
                'employee_name' => auth()->user()?->nhanVien?->HoTen,
                'confirmed_at' => $completedAt->toISOString(),
            ];
            $inspection->update([
                'DuLieuThucTe' => [...$snapshot, '_decision' => $decision],
                'CapNhatLuc' => $completedAt,
            ]);

            if ($changed && ! $draft) {
                $accountId = $locked->khachHang?->taiKhoan?->TaiKhoanID;
                if (! $accountId) {
                    throw ValidationException::withMessages(['booking' => 'Khách hàng chưa có tài khoản mobile để nhận thông báo điều chỉnh kiểm kê.']);
                }

                $this->notificationService->create([
                    'TaiKhoanID' => $accountId,
                    'DonHangID' => $order->DonHangID,
                    'LoaiThongBao' => 'booking_inspection_updated',
                    'TieuDe' => 'Cập nhật kiểm kê Booking',
                    'NoiDung' => 'Nhân viên đã đối soát và điều chỉnh thông tin đồ thực tế cho Booking '.$locked->MaBooking.'. Đơn hàng '.$order->MaDonHang.' đã được tạo.',
                    'DaDoc' => false,
                ], $locked);
            }

            $draft?->delete();

            return $order;
        });
    }

    public function saveInspectionDraft(Booking $booking, array $data): Booking
    {
        return DB::transaction(function () use ($booking, $data): Booking {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->BookingID);
            if ($locked->TrangThai !== BookingStatus::Confirmed->value || $this->hasConvertedOrder($locked)) {
                throw ValidationException::withMessages(['booking' => 'Booking không còn ở trạng thái cho phép lưu kết quả kiểm kê.']);
            }
            $employeeId = $this->requireAuthenticatedEmployeeId('booking', 'Cần nhân viên đang hoạt động để lưu kết quả kiểm kê.');
            $intake = BookingGiaoNhan::query()->where('BookingID', $locked->BookingID)->where('LoaiGiaoNhan', 'NHAN_DO')->lockForUpdate()->firstOrFail();
            $this->assertAssignedIntakeHandler($intake, $employeeId);
            if (! in_array($intake->TrangThai, [DeliveryStatus::Pending->dbValue(), DeliveryStatus::Picking->dbValue()], true)
                || $intake->ThoiGianThucTe !== null || $intake->DonHangID !== null || $intake->BookingKiemKeID !== null
                || BookingKiemKe::query()->where('BookingID', $locked->BookingID)->exists()) {
                throw ValidationException::withMessages(['booking' => 'Phiếu nhận không còn ở trạng thái cho phép lưu kiểm kê.']);
            }

            $data['customer_id'] = $locked->KhachHangID;
            CatalogAvailability::assertItemsAvailable($data['items'], $locked);
            $data['items'] = $this->preserveBookingItemNotes($locked, $data['items']);
            $feeChanged = $data['method'] !== $locked->HinhThucNhanDo
                || ($data['address'] ?? null) !== $locked->DiaChiNhan
                || $data['return_method'] !== $locked->HinhThucTraDo
                || ($data['return_address'] ?? null) !== $locked->DiaChiTra;
            $fees = $feeChanged ? app(DeliveryFeeService::class)->quote(
                $data['method'], $data['address'] ?? null, $data['return_method'], $data['return_address'] ?? null,
            ) : null;
            $promotionId = isset($data['promotion_id']) ? (int) $data['promotion_id'] : null;
            $requestedPoints = (int) ($data['points_used'] ?? 0);
            if ($fees) {
                $locked->forceFill([
                    'PickupDeliveryFee' => $fees['pickup_fee'], 'DeliveryFee' => $fees['return_fee'],
                ]);
            }
            $estimate = $this->orderService->estimateBookingWithRewards(
                $locked, $data['items'], $promotionId, $requestedPoints, true,
            );
            if ($promotionId !== null && $estimate['promotion_warning'] !== null) {
                throw ValidationException::withMessages(['promotion_id' => $estimate['promotion_warning']]);
            }
            if ($requestedPoints > (int) $estimate['available_points']) {
                throw ValidationException::withMessages(['points_used' => 'Số dư điểm đã thay đổi. Vui lòng tính lại giá.']);
            }

            $previousPromotionId = $locked->KhuyenMaiID === null ? null : (int) $locked->KhuyenMaiID;
            $promotionReserved = (bool) $locked->KhuyenMaiDaTru && $previousPromotionId === $promotionId;
            if ($previousPromotionId !== $promotionId && $locked->KhuyenMaiDaTru) {
                KhuyenMai::whereKey($previousPromotionId)->whereNotNull('SoLuongSuDung')->increment('SoLuongSuDung');
            }
            if ($promotionId !== null && ! $promotionReserved) {
                $promotion = KhuyenMai::query()->whereKey($promotionId)->lockForUpdate()->first();
                if (! $promotion || ! $promotion->isValid()
                    || Booking::query()->where('KhachHangID', $locked->KhachHangID)
                        ->where('KhuyenMaiID', $promotionId)->where('TrangThai', '<>', BookingStatus::Cancelled->value)
                        ->where('BookingID', '<>', $locked->BookingID)->exists()) {
                    throw ValidationException::withMessages(['promotion_id' => 'Mã giảm giá vừa hết hạn hoặc đã được dùng cho lịch đặt khác.']);
                }
                if ($promotion->SoLuongSuDung !== null) {
                    $affected = KhuyenMai::query()->whereKey($promotionId)->where('SoLuongSuDung', '>', 0)->decrement('SoLuongSuDung');
                    if ($affected !== 1) {
                        throw ValidationException::withMessages(['promotion_id' => 'Mã giảm giá vừa hết lượt sử dụng.']);
                    }
                    $promotionReserved = true;
                }
            }
            if ($locked->DiemDaTru && (int) $locked->DiemSuDung > 0) {
                KhachHang::find($locked->KhachHangID)?->addPoints((int) $locked->DiemSuDung);
            }
            if ($estimate['DiemSuDung'] > 0 && ! KhachHang::find($locked->KhachHangID)?->deductPoints((int) $estimate['DiemSuDung'])) {
                throw ValidationException::withMessages(['points_used' => 'Số dư điểm đã thay đổi. Vui lòng tính lại giá.']);
            }
            $before = $this->bookingAuditSnapshot($locked);
            $beforeItems = $this->bookingItemsAuditSnapshot($locked);
            $locked->forceFill([
                ...$this->mapRequestData($data),
                ...($fees ? [
                    'PickupDeliveryFee' => $fees['pickup_fee'], 'DeliveryFee' => $fees['return_fee'],
                    'PickupDistanceMeters' => $fees['pickup_distance_meters'], 'DeliveryDistanceMeters' => $fees['return_distance_meters'],
                ] : []),
                'KhuyenMaiID' => $promotionId,
                'KhuyenMaiDaTru' => $promotionReserved,
                'DiemSuDung' => $estimate['DiemSuDung'],
                'TienGiamDoDiem' => $estimate['TienGiamDoDiem'],
                'DiemDaTru' => $estimate['DiemSuDung'] > 0,
                'NgayCapNhat' => now(),
            ])->saveQuietly();
            if ($fees) {
                foreach ([['NHAN_DO', 'method', 'address', 'pickup_fee'], ['GIAO_DO', 'return_method', 'return_address', 'return_fee']] as [$legType, $methodKey, $addressKey, $feeKey]) {
                    BookingGiaoNhan::query()->where('BookingID', $locked->BookingID)->where('LoaiGiaoNhan', $legType)->update([
                        'HinhThuc' => $data[$methodKey],
                        'DiaChi' => $data[$methodKey] === 'Tại nhà' ? ($data[$addressKey] ?? null) : null,
                        'PhiGiaoNhan' => $fees[$feeKey],
                    ]);
                }
            }
            if ($data['scheduled_date'] !== $locked->NgayHen?->format('Y-m-d')
                || $data['scheduled_time'] !== substr((string) $locked->GioHen, 0, 5)) {
                BookingGiaoNhan::query()->where('BookingID', $locked->BookingID)->where('LoaiGiaoNhan', 'NHAN_DO')
                    ->update(['ThoiGianDuKien' => $data['scheduled_date'].' '.$data['scheduled_time']]);
            }
            $this->syncBookingItems($locked, $data['items']);
            $locked->refresh();
            $changed = $before !== $this->bookingAuditSnapshot($locked)
                || $beforeItems !== $this->bookingItemsAuditSnapshot($locked);
            $changed = $changed || (bool) StaffBookingDraft::query()->whereKey($locked->BookingID)->value('CoChenhLech');
            if ($changed) {
                $this->recordBookingAudit($locked, $before, $this->bookingAuditSnapshot($locked), $beforeItems, $this->bookingItemsAuditSnapshot($locked));
            }
            StaffBookingDraft::query()->updateOrCreate(
                ['BookingID' => $locked->BookingID],
                ['Payload' => $data, 'CoChenhLech' => $changed, 'CapNhatLuc' => now()],
            );

            return $locked->fresh(['khachHang', 'chiTietBookings.dichVu', 'chiTietBookings.loaiDoGiat', 'chiTietBookings.donViTinh']);
        });
    }

    public function canInspect(Booking $booking): bool
    {
        return $this->inspectionUnavailableReason($booking) === null;
    }

    public function assertCanInspect(Booking $booking): void
    {
        $reason = $this->inspectionUnavailableReason($booking);
        if ($reason !== null) {
            throw ValidationException::withMessages(['booking' => $reason]);
        }
    }

    private function inspectionUnavailableReason(Booking $booking): ?string
    {
        if ($booking->TrangThai !== BookingStatus::Confirmed->value || $this->hasConvertedOrder($booking)) {
            return 'Booking không còn ở trạng thái cho phép kiểm kê.';
        }

        $intake = BookingGiaoNhan::query()
            ->where('BookingID', $booking->BookingID)
            ->where('LoaiGiaoNhan', 'NHAN_DO')
            ->first();

        if (
            ! $intake
            || ! in_array($intake->TrangThai, [
                DeliveryStatus::Pending->dbValue(),
                DeliveryStatus::Picking->dbValue(),
            ], true)
            || $intake->ThoiGianThucTe !== null
            || $intake->DonHangID !== null
            || $intake->BookingKiemKeID !== null
            || BookingKiemKe::query()->where('BookingID', $booking->BookingID)->exists()
        ) {
            return 'Phiếu nhận không còn ở trạng thái cho phép kiểm kê.';
        }

        $employeeId = auth()->user()?->NhanVienID;
        if (! $employeeId || ! NhanVien::query()->whereKey($employeeId)->where('TrangThai', 'Hoạt động')->exists()) {
            return 'Tài khoản cần hồ sơ nhân viên đang hoạt động để kiểm kê.';
        }
        if ($intake->NhanVienID === null || (int) $intake->NhanVienID !== (int) $employeeId) {
            return 'Chỉ nhân viên được phân công mới có thể kiểm tra và xử lý phiếu nhận đồ.';
        }

        return null;
    }

    public function refuseQuote(Booking $booking, array $data): Booking
    {
        return DB::transaction(function () use ($booking, $data): Booking {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->BookingID);
            if ($locked->TrangThai !== BookingStatus::Confirmed->value || $this->hasConvertedOrder($locked)) {
                throw ValidationException::withMessages([
                    'booking' => 'Chỉ Booking đã xác nhận, chưa tạo đơn mới có thể hủy do khách từ chối báo giá.',
                ]);
            }

            $employeeId = $this->requireAuthenticatedEmployeeId(
                'booking',
                'Tài khoản cần hồ sơ nhân viên đang hoạt động để xử lý từ chối báo giá.',
            );
            $intake = BookingGiaoNhan::query()
                ->where('BookingID', $locked->BookingID)
                ->where('LoaiGiaoNhan', 'NHAN_DO')
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertAssignedIntakeHandler($intake, $employeeId);
            if (
                ! in_array($intake->TrangThai, [
                    DeliveryStatus::Pending->dbValue(),
                    DeliveryStatus::Picking->dbValue(),
                ], true)
                || $intake->ThoiGianThucTe !== null
                || $intake->DonHangID !== null
                || DonHang::query()->where('BookingID', $locked->BookingID)->exists()
            ) {
                throw ValidationException::withMessages([
                    'booking' => 'Không thể hủy theo luồng từ chối báo giá sau khi đồ đã được tiếp nhận hoặc đã tạo đơn.',
                ]);
            }

            $data = $this->useOriginalBookingDetails($locked, $data);
            CatalogAvailability::assertItemsAvailable($data['items'], $locked);
            $data['items'] = $this->preserveBookingItemNotes($locked, $data['items']);
            $snapshot = $this->inspectionSnapshot($data);
            $changed = $this->inspectionDiffersFromBooking($locked, $snapshot);
            $inspection = BookingKiemKe::query()
                ->where('BookingID', $locked->BookingID)
                ->lockForUpdate()
                ->first();
            $timestamp = now();
            $reason = trim($data['rejection_reason']);
            $previousSnapshots = $inspection?->DuLieuThucTe['_previous_versions'] ?? [];
            if ($inspection !== null) {
                $previousSnapshots[] = $inspection->DuLieuThucTe;
            }
            $snapshot['_decision'] = [
                'customer_accepted' => false,
                'reason' => $reason,
                'employee_id' => $employeeId,
                'employee_name' => auth()->user()?->nhanVien?->HoTen,
                'decided_at' => $timestamp->toISOString(),
                'charged_total' => 0,
            ];
            if ($previousSnapshots !== []) {
                $snapshot['_previous_versions'] = $previousSnapshots;
            }

            if ($inspection === null) {
                $inspection = BookingKiemKe::query()->create([
                    'BookingID' => $locked->BookingID,
                    'DuLieuThucTe' => $snapshot,
                    'CoChenhLech' => $changed,
                    'PhienBan' => 1,
                    'CapNhatLuc' => $timestamp,
                ]);
            } else {
                $inspection->update([
                    'DuLieuThucTe' => $snapshot,
                    'CoChenhLech' => $changed,
                    'PhienBan' => $inspection->PhienBan + 1,
                    'CapNhatLuc' => $timestamp,
                ]);
            }

            $intake->update([
                'TrangThai' => DeliveryStatus::Cancelled->dbValue(),
                'BookingKiemKeID' => $inspection->BookingKiemKeID,
            ]);
            BookingGiaoNhan::query()
                ->where('BookingID', $locked->BookingID)
                ->where('LoaiGiaoNhan', 'GIAO_DO')
                ->where('TrangThai', DeliveryStatus::Pending->dbValue())
                ->whereNull('ThoiGianThucTe')
                ->update(['TrangThai' => DeliveryStatus::Cancelled->dbValue()]);

            $before = $this->bookingAuditSnapshot($locked);
            $beforeItems = $this->bookingItemsAuditSnapshot($locked);
            $this->releaseLegacyPoints($locked);
            $locked->forceFill([
                'TrangThai' => BookingStatus::Cancelled->value,
                'NgayCapNhat' => $timestamp,
            ])->saveQuietly();
            $this->recordBookingAudit(
                $locked,
                $before,
                $this->bookingAuditSnapshot($locked),
                $beforeItems,
                $beforeItems,
                $reason,
            );

            return $locked->fresh(['giaoNhanDuKiens', 'kiemKe', 'donHangs']);
        });
    }

    private function assertAssignedIntakeHandler(BookingGiaoNhan $intake, int $employeeId): void
    {
        if ($intake->NhanVienID === null || (int) $intake->NhanVienID !== $employeeId) {
            throw ValidationException::withMessages([
                'booking' => 'Chỉ nhân viên được phân công mới có thể kiểm tra và xử lý phiếu nhận đồ.',
            ]);
        }
    }

    private function useOriginalBookingDetails(Booking $booking, array $data): array
    {
        return array_replace($data, [
            'customer_id' => $booking->KhachHangID,
            'method' => $booking->HinhThucNhanDo,
            'address' => $booking->DiaChiNhan,
            'return_method' => $booking->HinhThucTraDo,
            'return_address' => $booking->DiaChiTra,
            'scheduled_date' => $booking->NgayHen?->format('Y-m-d'),
            'scheduled_time' => $booking->GioHen?->format('H:i'),
            'notes' => $booking->GhiChu,
        ]);
    }

    private function inspectionSnapshot(array $data): array
    {
        $fields = ['method', 'return_method', 'address', 'return_address', 'scheduled_date', 'scheduled_time', 'notes'];
        $items = array_map(fn (array $item): array => array_intersect_key($item, array_flip([
            'DichVuID', 'LoaiDoGiatID', 'DonViTinhID', 'SoLuong', 'KhoiLuong', 'TinhTrangTruocKhiGiat', 'GhiChu',
        ])), $data['items']);

        return ['items' => $items, 'booking' => array_intersect_key($data, array_flip($fields))];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function preserveBookingItemNotes(Booking $booking, array $items): array
    {
        $noteQueues = [];
        foreach ($booking->chiTietBookings()->orderBy('ChiTietBookingID')->get([
            'DichVuID',
            'LoaiDoGiatID',
            'DonViTinhID',
            'GhiChu',
        ]) as $originalItem) {
            $key = $this->bookingItemNoteKey($originalItem->getAttributes());
            $noteQueues[$key][] = $originalItem->GhiChu;
        }

        foreach ($items as &$item) {
            if (array_key_exists('GhiChu', $item)) {
                continue;
            }

            $key = $this->bookingItemNoteKey($item);
            if (isset($noteQueues[$key]) && $noteQueues[$key] !== []) {
                $item['GhiChu'] = array_shift($noteQueues[$key]);
            }
        }
        unset($item);

        return $items;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function bookingItemNoteKey(array $item): string
    {
        return implode(':', [
            (int) ($item['DichVuID'] ?? 0),
            (int) ($item['LoaiDoGiatID'] ?? 0),
            (int) ($item['DonViTinhID'] ?? 0),
        ]);
    }

    private function inspectionDiffersFromBooking(Booking $booking, array $snapshot): bool
    {
        $input = $snapshot['booking'];
        foreach (['method' => 'HinhThucNhanDo', 'return_method' => 'HinhThucTraDo'] as $key => $column) {
            if (($input[$key] ?? null) !== $booking->{$column}) {
                return true;
            }
        }
        if ($booking->HinhThucNhanDo === 'Tại nhà' && ($input['address'] ?? null) !== $booking->DiaChiNhan) {
            return true;
        }
        if ($booking->HinhThucTraDo === 'Tại nhà' && ($input['return_address'] ?? null) !== $booking->DiaChiTra) {
            return true;
        }
        if (($input['scheduled_date'] ?? null) !== $booking->NgayHen?->format('Y-m-d')
            || ($input['scheduled_time'] ?? null) !== $booking->GioHen?->format('H:i')) {
            return true;
        }
        if (array_key_exists('notes', $input)
            && trim((string) $input['notes']) !== trim((string) ($booking->GhiChu ?? ''))) {
            return true;
        }

        $declared = $booking->chiTietBookings()->get();
        if ($declared->isEmpty()) {
            return false; // Khách chọn để nhân viên kiểm kê và nhập toàn bộ đồ thực tế.
        }
        $hasDeclaredCondition = $declared->contains(
            fn ($item): bool => trim((string) $item->getAttribute('TinhTrangTruocKhiGiat')) !== '',
        );
        $itemKey = static fn (array $item): string => implode(':', [
            (int) ($item['DichVuID'] ?? 0), (int) ($item['LoaiDoGiatID'] ?? 0),
            (int) ($item['DonViTinhID'] ?? 0), (float) ($item['SoLuong'] ?? 0),
            (float) ($item['KhoiLuong'] ?? 0), trim((string) ($item['GhiChu'] ?? '')),
        ]).($hasDeclaredCondition ? ':'.trim((string) ($item['TinhTrangTruocKhiGiat'] ?? '')) : '');
        $expected = $declared->map(fn ($item): string => $itemKey($item->only([
            'DichVuID', 'LoaiDoGiatID', 'DonViTinhID', 'SoLuong', 'KhoiLuong', 'GhiChu',
            'TinhTrangTruocKhiGiat',
        ])))->sort()->values()->all();
        $actual = collect($snapshot['items'])->map($itemKey)->sort()->values()->all();

        if ($expected !== $actual) {
            return true;
        }

        $priceTuples = collect($snapshot['items'])
            ->map(fn (array $item): array => array_intersect_key($item, array_flip([
                'DichVuID', 'LoaiDoGiatID', 'DonViTinhID',
            ])))
            ->unique(fn (array $tuple): string => implode(':', $tuple))
            ->values()
            ->all();
        $currentPrices = $this->pricingService->getLatestPricingForTuples($priceTuples);
        $priceKey = static fn (array $item, ?float $price): string => implode(':', [
            (int) ($item['DichVuID'] ?? 0),
            (int) ($item['LoaiDoGiatID'] ?? 0),
            (int) ($item['DonViTinhID'] ?? 0),
            $price === null ? 'missing' : number_format($price, 2, '.', ''),
        ]);
        $expectedPrices = $declared->map(fn ($item): string => $priceKey(
            $item->getAttributes(),
            (float) $item->DonGia,
        ))->sort()->values()->all();
        $actualPrices = collect($snapshot['items'])->map(function (array $item) use ($currentPrices, $priceKey): string {
            $tupleKey = implode(':', [
                (int) ($item['DichVuID'] ?? 0),
                (int) ($item['LoaiDoGiatID'] ?? 0),
                (int) ($item['DonViTinhID'] ?? 0),
            ]);

            return $priceKey($item, isset($currentPrices[$tupleKey]) ? (float) $currentPrices[$tupleKey]->DonGia : null);
        })->sort()->values()->all();

        return $expectedPrices !== $actualPrices;
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

            if ($lockedBooking->TrangThai === BookingStatus::Confirmed->value
                && ! $hasConvertedOrder) {
                throw ValidationException::withMessages(['booking' => 'Booking đã xác nhận chỉ được thay đổi qua kiểm kê thực tế.']);
            }

            if (
                $hasConvertedOrder
                && array_key_exists('staff_id', $data)
                && (int) $data['staff_id'] !== (int) $lockedBooking->NhanVienID
            ) {
                throw ValidationException::withMessages([
                    'staff_id' => 'Không thể thay đổi nhân viên phụ trách sau khi Booking đã được chuyển thành đơn hàng.',
                ]);
            }

            $assignedEmployeeId = null;
            if ($hasConvertedOrder) {
                unset($data['staff_id']);
            } elseif (array_key_exists('staff_id', $data)) {
                $assignedEmployeeId = $this->requireAuthenticatedEmployeeId(
                    'staff_id',
                    'Tài khoản hiện tại chưa liên kết hồ sơ nhân viên để cập nhật Booking.',
                );
                unset($data['staff_id']);
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

            if (
                ($data['status'] ?? null) === BookingStatus::Completed->value
                && $lockedBooking->TrangThai !== BookingStatus::Completed->value
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Chỉ có thể hoàn thành Booking sau khi bàn giao thành công qua phiếu giao trả.',
                ]);
            }

            if ($hasConvertedOrder && array_key_exists('items', $data)) {
                throw ValidationException::withMessages([
                    'items' => 'Không thể sửa các dòng dịch vụ sau khi Booking đã được chuyển thành đơn hàng.',
                ]);
            }

            unset($data['customer_id']);
            $mappedData = $this->mapRequestData($data);
            if ($assignedEmployeeId !== null) {
                $mappedData['NhanVienID'] = $assignedEmployeeId;
            }
            $wasPending = $lockedBooking->TrangThai === BookingStatus::Pending->value;
            $isBeingConfirmed = ($data['status'] ?? null) === BookingStatus::Confirmed->value;

            if ($wasPending && $isBeingConfirmed) {
                throw ValidationException::withMessages([
                    'status' => 'Hãy dùng thao tác “Xác nhận Booking” để lập phiếu nhận trước khi kiểm kê.',
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

        if (array_key_exists('HinhThucTraDo', $mapped)) {
            $mapped['HinhThucGiaoDo'] = $mapped['HinhThucTraDo'];
        }
        if (array_key_exists('DiaChiTra', $mapped)) {
            $mapped['DiaChiGiao'] = $mapped['DiaChiTra'];
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

            if ($lockedBooking->TrangThai === BookingStatus::Confirmed->value) {
                return false;
            }

            $this->releaseLegacyPoints($lockedBooking);

            return (bool) $lockedBooking->delete();
        });
    }

    /**
     * Kiểm tra thực tế và tạo đơn trong một giao dịch, có khóa chống tạo trùng.
     *
     * @param  int|null  $employeeId  Legacy caller value; the authenticated account is authoritative.
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $bookingData
     */
    public function inspectBookingAndCreateOrder(
        Booking $booking,
        ?int $employeeId,
        array $items,
        int $pointsUsed = 0,
        bool $useAllAvailablePoints = false,
        array $bookingData = [],
        bool $recalculateDeliveryFee = false,
        bool $useSavedBookingPoints = false,
        bool $requirePromotion = false,
    ): DonHang {
        return DB::transaction(function () use ($booking, $items, $pointsUsed, $useAllAvailablePoints, $bookingData, $recalculateDeliveryFee, $useSavedBookingPoints, $requirePromotion): DonHang {
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->BookingID);
            $employeeId = $this->requireAuthenticatedEmployeeId(
                'booking',
                'Tài khoản hiện tại chưa liên kết hồ sơ nhân viên để xác nhận Booking.',
            );
            $existing = $this->findOrderForBooking($lockedBooking);
            if ($existing !== null) {
                return $existing;
            }

            if ($lockedBooking->TrangThai !== BookingStatus::Confirmed->value) {
                throw ValidationException::withMessages([
                    'booking' => 'Hãy xác nhận Booking trước khi kiểm kê và tạo đơn.',
                ]);
            }

            if (! BookingGiaoNhan::query()->where('BookingID', $lockedBooking->BookingID)
                ->where('LoaiGiaoNhan', 'NHAN_DO')
                ->whereIn('TrangThai', [
                    DeliveryStatus::Picking->dbValue(),
                    DeliveryStatus::Completed->dbValue(),
                    'Đã nhận',
                ])->exists()) {
                throw ValidationException::withMessages(['booking' => 'Phiếu nhận chưa ở trạng thái cho phép kiểm kê và tạo đơn.']);
            }

            $inspection = BookingKiemKe::query()->where('BookingID', $lockedBooking->BookingID)->lockForUpdate()->first();
            $expectedSnapshot = $this->inspectionSnapshot(array_merge($bookingData, ['items' => $items]));
            if (! $inspection || $inspection->DuLieuThucTe != $expectedSnapshot) {
                throw ValidationException::withMessages(['booking' => 'Bản kiểm kê chưa được lưu hoặc không khớp với dữ liệu đang tạo đơn.']);
            }
            if ($recalculateDeliveryFee) {
                throw ValidationException::withMessages([
                    'recalculate_delivery_fee' => 'Phí giao nhận đã lưu từ Booking không được tính lại trong lúc kiểm kê.',
                ]);
            }

            if ($useSavedBookingPoints) {
                $pointsUsed = (int) $lockedBooking->DiemSuDung;
            }

            $before = $this->bookingAuditSnapshot($lockedBooking);
            $beforeItems = $this->bookingItemsAuditSnapshot($lockedBooking);
            unset($bookingData['customer_id'], $bookingData['status'], $bookingData['staff_id']);
            $lockedBooking->fill($this->mapRequestData($bookingData));

            if ($useAllAvailablePoints) {
                $pointsUsed = (int) (KhachHang::query()->find($lockedBooking->KhachHangID)?->points() ?? 0)
                    + ($lockedBooking->getAttribute('DiemDaTru') ? (int) $lockedBooking->getAttribute('DiemSuDung') : 0);
            }

            $order = $this->orderService->createFromBooking($lockedBooking, $items, $employeeId, $pointsUsed, $requirePromotion);
            $this->recordBookingAudit(
                $lockedBooking, $before, $this->bookingAuditSnapshot($lockedBooking),
                $beforeItems, $this->bookingItemsAuditSnapshot($lockedBooking),
            );

            return $order->fresh();
        });
    }

    private function requireAuthenticatedEmployeeId(string $field, string $missingProfileMessage): int
    {
        $employeeId = auth()->user()?->NhanVienID;

        if (! $employeeId) {
            throw ValidationException::withMessages([$field => $missingProfileMessage]);
        }

        $employeeId = (int) $employeeId;
        EmployeeAssignment::assertAssignable($employeeId, $field);

        return $employeeId;
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
        CatalogAvailability::assertItemsAvailable($items, $booking);
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
            'HinhThucGiaoDo', 'DiaChiGiao', 'PhuongThucThanhToan',
            'PickupDistanceMeters', 'DeliveryDistanceMeters', 'PickupDeliveryFee', 'DeliveryFee',
            'KhuyenMaiID', 'KhuyenMaiDaTru', 'DiemSuDung', 'TienGiamDoDiem', 'DiemDaTru',
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
        ?string $reason = null,
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
            'LyDo' => $reason,
            'ThoiGian' => now(),
            'IPAddress' => request()->ip(),
            'UserAgent' => request()->userAgent(),
        ]);
    }
}
