<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BookingStatus;
use App\Enums\DeliveryStatus;
use App\Http\Controllers\Api\ApiController;
use App\Models\BangGia;
use App\Models\Booking;
use App\Models\BookingGiaoNhan;
use App\Models\DichVu;
use App\Models\DonHang;
use App\Models\GiaoNhan;
use App\Models\LoaiDoGiat;
use App\Models\KhuyenMai;
use App\Models\StaffBookingDraft;
use App\Models\User;
use App\Services\BookingService;
use App\Services\DeliveryService;
use App\Services\DeliveryFeeService;
use App\Services\OrderService;
use App\Services\PricingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class StaffMobileController extends ApiController
{
    public function __construct(private BookingService $bookings, private OrderService $orders, private DeliveryService $deliveries) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $user = User::query()->where('Email', $data['email'])->with('nhanVien')->first();
        if (! $user || ! Hash::check($data['password'], $user->getAuthPassword()) || ! $user->isActive()
            || ! $user->isStaff() || ! $user->nhanVien || $user->nhanVien->TrangThai !== 'Hoạt động') {
            return response()->json(['message' => 'Email hoặc mật khẩu nhân viên không đúng.'], 422);
        }
        $token = $user->issueStaffApiToken();

        return response()->json(['data' => ['access_token' => $token, 'user' => [
            'id' => $user->TaiKhoanID, 'name' => $user->nhanVien->HoTen, 'email' => $user->Email,
            'employee_id' => $user->NhanVienID, 'roles' => $user->roleNames(),
        ]]]);
    }

    public function logout(Request $request): JsonResponse
    {
        DB::table('StaffApiToken')->where('TokenHash', $request->attributes->get('staff_api_token_hash'))->delete();
        return response()->json(['success' => true]);
    }

    public function profile(): JsonResponse
    {
        return response()->json(['data' => $this->profileData(Auth::user())]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = Auth::user();
        $request->merge([
            'phone' => preg_replace('/[\s().-]/', '', trim((string) $request->input('phone'))),
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:15', 'regex:/^\+?[0-9]{8,15}$/', 'unique:TaiKhoan,SoDienThoai,'.$user->TaiKhoanID.',TaiKhoanID'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($user, $data): void {
            $user->nhanVien->forceFill([
                'HoTen' => trim($data['name']),
                'SoDienThoai' => trim($data['phone']),
                'DiaChi' => array_key_exists('address', $data)
                    ? trim((string) $data['address'])
                    : $user->nhanVien->DiaChi,
            ])->save();
            $user->forceFill(['SoDienThoai' => trim($data['phone'])])->save();
        });

        return response()->json(['data' => $this->profileData($user->fresh()->load('nhanVien'))]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = Auth::user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&_-]).+$/'],
        ], [
            'new_password.regex' => 'Mật khẩu phải chứa chữ hoa, chữ thường, số và ký tự đặc biệt.',
            'new_password.confirmed' => 'Mật khẩu xác nhận không khớp.',
        ]);
        if (! Hash::check($data['current_password'], $user->getAuthPassword())) {
            throw ValidationException::withMessages(['current_password' => 'Mật khẩu hiện tại không đúng.']);
        }

        $user->forceFill(['MatKhau' => Hash::make($data['new_password'])])->save();
        DB::table('StaffApiToken')->where('TaiKhoanID', $user->TaiKhoanID)
            ->where('TokenHash', '<>', $request->attributes->get('staff_api_token_hash'))->delete();

        return response()->json(['success' => true]);
    }

    private function profileData(User $user): array
    {
        $employee = $user->nhanVien;

        return [
            'id' => $user->TaiKhoanID,
            'name' => $employee->HoTen,
            'email' => $user->Email,
            'phone' => $employee->SoDienThoai ?: $user->SoDienThoai,
            'avatar_url' => $user->AvatarURL,
            'address' => $employee->DiaChi,
            'position' => $employee->ChucDanh,
            'roles' => $user->roleNames(),
        ];
    }

    public function bookings(): JsonResponse
    {
        $employeeId = Auth::user()->NhanVienID;
        $rows = Booking::query()->where('TrangThai', BookingStatus::Confirmed->value)->where('HinhThucNhanDo', 'Tại nhà')
            ->whereHas('giaoNhanDuKiens', fn ($q) => $q->where('LoaiGiaoNhan', 'NHAN_DO')->where('NhanVienID', $employeeId)
                ->whereIn('TrangThai', [DeliveryStatus::Pending->dbValue(), DeliveryStatus::Picking->dbValue()]))
            ->with(['khachHang', 'chiTietBookings.dichVu', 'chiTietBookings.loaiDoGiat', 'chiTietBookings.donViTinh',
                'requestedServices.dichVu', 'requestedServices.loaiDoGiat', 'requestedServices.donViTinh', 'giaoNhanDuKiens'])
            ->latest('NgayTao')->get()->map(fn (Booking $b) => $this->bookingData($b));
        return response()->json(['data' => $rows]);
    }

    public function bookingHistory(): JsonResponse
    {
        $employeeId = Auth::user()->NhanVienID;
        $rows = Booking::query()
            ->whereHas('giaoNhanDuKiens', fn ($q) => $q
                ->where('LoaiGiaoNhan', 'NHAN_DO')
                ->where('NhanVienID', $employeeId)
                ->where('TrangThai', DeliveryStatus::Completed->dbValue())
                ->whereNotNull('BookingKiemKeID'))
            ->with(['khachHang', 'chiTietBookings.dichVu', 'chiTietBookings.loaiDoGiat', 'chiTietBookings.donViTinh', 'giaoNhanDuKiens'])
            ->latest('NgayTao')->get()->map(fn (Booking $b) => $this->bookingData($b, false));

        return response()->json(['data' => $rows]);
    }

    public function orders(): JsonResponse
    {
        $employeeId = Auth::user()->NhanVienID;
        $rows = $this->assignedOrdersForEmployee($employeeId)->with([
                'booking',
                'booking.giaoNhanDuKiens' => fn ($delivery) => $delivery->where('NhanVienID', $employeeId)->where('LoaiGiaoNhan', 'GIAO_DO')->where('HinhThuc', 'Tại nhà'),
                'giaoNhans' => fn ($delivery) => $delivery->where('NhanVienID', $employeeId)->where('LoaiGiaoNhan', 'GIAO_DO')->where('HinhThuc', 'Tại nhà'),
                'khachHang', 'khuyenMai', 'chiTietDonHangs.dichVu', 'chiTietDonHangs.loaiDoGiat', 'chiTietDonHangs.donViTinh',
            ])
            ->latest('NgayTao')->get();
        return response()->json(['data' => $rows->map(fn (DonHang $o) => [
            'DonHangID' => $o->DonHangID, 'BookingID' => $o->BookingID, 'MaDonHang' => $o->MaDonHang,
            'TrangThai' => $o->TrangThai, 'TongTien' => $o->TongTien, 'DiemSuDung' => $o->DiemSuDung,
            'TienGiamDoDiem' => $o->TienGiamDoDiem, 'TienGiamKhuyenMai' => $o->TienGiamKhuyenMai,
            'ThanhTien' => $o->ThanhTien, 'NgayTao' => $o->NgayTao?->toIso8601String(),
            'KhuyenMai' => $o->khuyenMai ? ['MaKhuyenMai' => $o->khuyenMai->MaKhuyenMai] : null,
            'KhachHang' => $o->khachHang ? ['HoTen' => $o->khachHang->HoTen, 'SoDienThoai' => $o->khachHang->SoDienThoai] : null,
            'CanConfirmDelivery' => $o->giaoNhans->isNotEmpty() || $o->booking?->giaoNhanDuKiens->isNotEmpty(),
            'Booking' => $o->booking ? [
                'BookingID' => $o->booking->BookingID, 'MaBooking' => $o->booking->MaBooking,
                'HinhThucNhanDo' => $o->booking->HinhThucNhanDo, 'HinhThucTraDo' => $o->booking->HinhThucTraDo,
                'DiaChiNhan' => $o->booking->DiaChiNhan, 'DiaChiTra' => $o->booking->DiaChiTra,
                'NgayHen' => $o->booking->NgayHen?->format('Y-m-d'), 'GioHen' => $o->booking->GioHen,
                'GhiChu' => $o->booking->GhiChu,
            ] : null,
            'ChiTietDonHang' => $o->chiTietDonHangs->map(fn ($i) => [
                'SoLuong' => $i->SoLuong, 'KhoiLuong' => $i->KhoiLuong, 'DonGia' => $i->DonGia, 'ThanhTien' => $i->ThanhTien,
                'DichVu' => ['TenDichVu' => $i->dichVu?->TenDichVu],
                'LoaiDoGiat' => ['TenLoaiDoGiat' => $i->loaiDoGiat?->TenLoaiDoGiat],
                'DonViTinh' => ['KyHieu' => $i->donViTinh?->KyHieu],
            ])->values(),
        ])->values()]);
    }

    public function updateOrderStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'string'], 'reason' => ['nullable', 'string', 'max:500']]);
        $employeeId = Auth::user()->NhanVienID;
        $order = $this->assignedOrdersForEmployee($employeeId)->findOrFail($id);
        if ((int) $order->NhanVienID !== (int) $employeeId
            && ! in_array($data['status'], ['Đang giao', 'Đã giao'], true)) {
            abort(403);
        }
        $homeDelivery = GiaoNhan::query()
            ->where('DonHangID', $id)
            ->where('NhanVienID', $employeeId)
            ->where('LoaiGiaoNhan', 'GIAO_DO')
            ->where('HinhThuc', 'Tại nhà')
            ->first();
        $bookingDelivery = BookingGiaoNhan::query()
            ->where('DonHangID', $id)
            ->where('NhanVienID', $employeeId)
            ->where('LoaiGiaoNhan', 'GIAO_DO')
            ->where('HinhThuc', 'Tại nhà')
            ->first();
        $hasHomeDelivery = GiaoNhan::query()->where('DonHangID', $id)->where('LoaiGiaoNhan', 'GIAO_DO')->where('HinhThuc', 'Tại nhà')->exists()
            || BookingGiaoNhan::query()->where('DonHangID', $id)->where('LoaiGiaoNhan', 'GIAO_DO')->where('HinhThuc', 'Tại nhà')->exists();
        if ($hasHomeDelivery && $data['status'] === 'Đã giao') {
            throw ValidationException::withMessages(['status' => 'Hãy xác nhận giao đồ trong màn hình chi tiết giao nhận.']);
        }
        if ($hasHomeDelivery && $data['status'] === 'Đang giao') {
            abort_unless($homeDelivery || $bookingDelivery, 403);
            $deliveryStatus = DeliveryStatus::parseForLeg(
                $homeDelivery?->TrangThai ?? $bookingDelivery?->TrangThai,
                'GIAO_DO',
            );
            if ($order->TrangThai === 'Đang giao' && $deliveryStatus === DeliveryStatus::Delivering) {
                return response()->json(['data' => ['id' => $order->DonHangID, 'status' => $order->TrangThai]]);
            }
            if ($order->TrangThai !== 'Hoàn thành giặt') {
                throw ValidationException::withMessages(['status' => 'Đơn hàng chưa sẵn sàng để bắt đầu giao.']);
            }
            if ($homeDelivery) {
                $this->deliveries->advanceProgress($homeDelivery);
            } else {
                $this->deliveries->advanceBookingDelivery($bookingDelivery);
            }
            return response()->json(['data' => [
                'id' => $order->DonHangID,
                'status' => DonHang::query()->whereKey($order->DonHangID)->value('TrangThai'),
            ]]);
        }
        $order = $this->orders->updateStatus($order, $data['status'], reason: $data['reason'] ?? null);
        return response()->json(['data' => ['id' => $order->DonHangID, 'status' => $order->TrangThai]]);
    }

    public function advanceDelivery(int $id): JsonResponse
    {
        $delivery = GiaoNhan::query()
            ->where('DonHangID', $id)
            ->where('NhanVienID', Auth::user()->NhanVienID)
            ->where('LoaiGiaoNhan', 'GIAO_DO')
            ->where('HinhThuc', 'Tại nhà')
            ->first();
        if ($delivery) {
            $delivery = $this->deliveries->advanceProgress($delivery);
            $status = DeliveryStatus::parseForLeg($delivery->TrangThai, $delivery->LoaiGiaoNhan)->value;
            $orderStatus = $delivery->donHang()->value('TrangThai');
        } else {
            $bookingDelivery = BookingGiaoNhan::query()
                ->where('DonHangID', $id)
                ->where('NhanVienID', Auth::user()->NhanVienID)
                ->where('LoaiGiaoNhan', 'GIAO_DO')
                ->where('HinhThuc', 'Tại nhà')
                ->firstOrFail();
            $bookingDelivery = $this->deliveries->advanceBookingDelivery($bookingDelivery);
            $status = DeliveryStatus::parseForLeg($bookingDelivery->TrangThai, $bookingDelivery->LoaiGiaoNhan)->value;
            $orderStatus = DonHang::query()->whereKey($id)->value('TrangThai');
        }
        return response()->json(['data' => [
            'delivery_status' => $status,
            'order_status' => $orderStatus,
        ]]);
    }

    private function assignedOrdersForEmployee(int $employeeId): Builder
    {
        return DonHang::query()->where(function ($query) use ($employeeId): void {
            $query->where(function ($assignedOrder) use ($employeeId): void {
                $assignedOrder->where('NhanVienID', $employeeId)
                    ->where(function ($order) use ($employeeId): void {
                        $order->whereNull('BookingID')
                            ->orWhereDoesntHave('booking.giaoNhanDuKiens', fn ($delivery) => $delivery
                                ->where('LoaiGiaoNhan', 'NHAN_DO')
                                ->where('NhanVienID', $employeeId));
                    });
            })
                ->orWhereHas('giaoNhans', fn ($delivery) => $delivery->where('NhanVienID', $employeeId)->where('LoaiGiaoNhan', 'GIAO_DO'))
                ->orWhereHas('booking.giaoNhanDuKiens', fn ($delivery) => $delivery->where('NhanVienID', $employeeId)->where('LoaiGiaoNhan', 'GIAO_DO'));
        });
    }

    public function booking(int $id): JsonResponse
    {
        return response()->json(['data' => $this->bookingData($this->assignedBooking($id))]);
    }

    public function estimate(Request $request, int $id): JsonResponse
    {
        $booking = $this->assignedBooking($id);
        $data = $this->validatedInspection($request);
        $this->bookings->assertCanInspect($booking);
        if ($data['method'] !== $booking->HinhThucNhanDo || ($data['address'] ?? null) !== $booking->DiaChiNhan
            || $data['return_method'] !== $booking->HinhThucTraDo || ($data['return_address'] ?? null) !== $booking->DiaChiTra) {
            $fees = app(DeliveryFeeService::class)->quote($data['method'], $data['address'] ?? null, $data['return_method'], $data['return_address'] ?? null);
            $booking->forceFill([
                'PickupDeliveryFee' => $fees['pickup_fee'], 'DeliveryFee' => $fees['return_fee'],
                'PickupDistanceMeters' => $fees['pickup_distance_meters'], 'DeliveryDistanceMeters' => $fees['return_distance_meters'],
            ])->syncOriginal();
        }
        $promotionId = $data['promotion_id'] ?? null;
        if ($promotionId !== null && ! $this->customerCanUsePromotion($booking, (int) $promotionId)) {
            throw ValidationException::withMessages(['promotion_id' => 'Mã giảm giá không còn khả dụng cho khách hàng này.']);
        }
        $estimate = $this->orders->estimateBookingWithRewards(
            $booking, $data['items'], $promotionId, (int) ($data['points_used'] ?? 0), true,
        );
        $estimate['pickup_distance_meters'] = (int) $booking->PickupDistanceMeters;
        $estimate['return_distance_meters'] = (int) $booking->DeliveryDistanceMeters;

        return response()->json(['data' => $estimate]);
    }

    public function draft(Request $request, int $id): JsonResponse
    {
        $data = $this->validatedInspection($request);
        $booking = $this->bookings->saveInspectionDraft($this->assignedBooking($id), $data);
        return response()->json(['data' => $this->bookingData($booking)]);
    }

    public function confirm(Request $request, int $id): JsonResponse
    {
        $data = $this->validatedInspection($request);
        $data['customer_accepted'] = $request->boolean('customer_agreed');
        if (! $data['customer_accepted']) {
            throw ValidationException::withMessages(['customer_agreed' => 'Cần xác nhận khách đã đồng ý bằng lời trước khi tạo đơn.']);
        }
        $order = $this->bookings->submitInspection($this->assignedBooking($id), $data);
        return response()->json(['data' => ['order_id' => $order->DonHangID, 'order_code' => $order->MaDonHang]]);
    }

    public function refuseQuote(Request $request, int $id): JsonResponse
    {
        $data = $this->validatedInspection($request, includeRejectionReason: true);
        $booking = $this->bookings->refuseQuote($this->assignedBooking($id), $data);

        return response()->json(['data' => ['id' => $booking->BookingID, 'status' => $booking->TrangThai]]);
    }

    private function assignedBooking(int $id): Booking
    {
        $booking = $this->bookings->find($id);
        $employeeId = (int) Auth::user()->NhanVienID;
        $intake = BookingGiaoNhan::query()->where('BookingID', $id)->where('LoaiGiaoNhan', 'NHAN_DO')->first();
        if (! $booking || $booking->HinhThucNhanDo !== 'Tại nhà' || ! $intake || (int) $intake->NhanVienID !== $employeeId) {
            abort(404);
        }
        return $booking;
    }

    private function validatedInspection(Request $request, bool $includeRejectionReason = false): array
    {
        $rules = [
            'method' => ['required', 'in:Tại nhà,Tại cửa hàng'],
            'address' => ['nullable', 'required_if:method,Tại nhà', 'string', 'max:255'],
            'return_method' => ['required', 'in:Tại nhà,Tại cửa hàng'],
            'return_address' => ['nullable', 'required_if:return_method,Tại nhà', 'string', 'max:255'],
            'scheduled_date' => ['required', 'date'], 'scheduled_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.DichVuID' => ['required', 'integer'], 'items.*.LoaiDoGiatID' => ['required', 'integer'],
            'items.*.DonViTinhID' => ['required', 'integer'], 'items.*.SoLuong' => ['nullable', 'numeric', 'min:1'],
            'items.*.KhoiLuong' => ['nullable', 'numeric', 'gt:0'],
            'items.*.TinhTrangTruocKhiGiat' => ['required', 'string', 'max:320'], 'items.*.GhiChu' => ['nullable', 'string', 'max:500'],
            'promotion_id' => ['nullable', 'integer', 'min:1'],
            'points_used' => ['nullable', 'integer', 'min:0'],
        ];
        if ($includeRejectionReason) {
            $rules['rejection_reason'] = ['required', 'string', 'max:500'];
        }
        return $request->validate($rules);
    }

    private function bookingData(Booking $booking, bool $includeCatalog = true): array
    {
        $draft = $includeCatalog ? StaffBookingDraft::query()->find($booking->BookingID) : null;
        $draftPayload = $draft?->Payload ?? [];
        $draftItems = array_values($draft?->Payload['items'] ?? []);
        $appointmentTime = $booking->GioHen?->format('H:i');
        $items = $booking->chiTietBookings->values()->map(fn ($i, $index) => [
            'service_id' => $i->DichVuID, 'garment_id' => $i->LoaiDoGiatID, 'unit_id' => $i->DonViTinhID,
            'quantity' => $i->SoLuong === null ? null : (float) $i->SoLuong, 'weight' => $i->KhoiLuong === null ? null : (float) $i->KhoiLuong,
            'unit_price' => (float) $i->DonGia, 'subtotal' => (float) $i->ThanhTien,
            'service_name' => $i->dichVu?->TenDichVu, 'garment_name' => $i->loaiDoGiat?->TenLoaiDoGiat,
            'unit_name' => $i->donViTinh?->KyHieu ?: $i->donViTinh?->TenDonViTinh,
            'condition' => $draftItems[$index]['TinhTrangTruocKhiGiat'] ?? '', 'note' => $i->GhiChu,
        ])->values();
        $existingTuples = $items->mapWithKeys(fn ($item) => [implode(':', [
            $item['service_id'], $item['garment_id'], $item['unit_id'],
        ]) => true]);
        if ($includeCatalog) {
            foreach ($booking->requestedServices as $requestedService) {
                $tuple = implode(':', [
                    $requestedService->DichVuID, $requestedService->LoaiDoGiatID, $requestedService->DonViTinhID,
                ]);
                if ($existingTuples->has($tuple)) {
                    continue;
                }
                $isWeight = $requestedService->donViTinh?->isWeightUnit() ?? false;
                $items->push([
                    'service_id' => $requestedService->DichVuID,
                    'garment_id' => $requestedService->LoaiDoGiatID,
                    'unit_id' => $requestedService->DonViTinhID,
                    'quantity' => $isWeight ? null : 0,
                    'weight' => $isWeight ? 0 : null,
                    'unit_price' => null,
                    'subtotal' => null,
                    'service_name' => $requestedService->dichVu?->TenDichVu,
                    'garment_name' => $requestedService->loaiDoGiat?->TenLoaiDoGiat,
                    'unit_name' => $requestedService->donViTinh?->KyHieu ?: $requestedService->donViTinh?->TenDonViTinh,
                    'condition' => '',
                    'note' => '',
                ]);
                $existingTuples->put($tuple, true);
            }
        }
        $catalog = null;
        if ($includeCatalog) {
            $selectedServiceIds = $booking->chiTietBookings->pluck('DichVuID')
                ->merge($booking->requestedServices->pluck('DichVuID'))->unique()->values();
            $selectedGarmentIds = $booking->chiTietBookings->pluck('LoaiDoGiatID')
                ->merge($booking->requestedServices->pluck('LoaiDoGiatID'))->unique()->values();
            $selectedCategoryIds = $booking->chiTietBookings->map(fn ($item) => $item->dichVu?->LoaiDichVuID)
                ->merge($booking->requestedServices->map(fn ($item) => $item->dichVu?->LoaiDichVuID))
                ->filter()->unique()->values();
            $prices = app(PricingService::class)->orderOptionsForRecord($booking)->map(fn ($p) => [
                'service_id' => $p->DichVuID, 'garment_id' => $p->LoaiDoGiatID, 'unit_id' => $p->DonViTinhID,
                'unit_name' => $p->donViTinh?->TenDonViTinh, 'unit_symbol' => $p->donViTinh?->KyHieu,
                'unit_price' => (float) $p->DonGia, 'is_weight' => BangGia::isWeightUnit($p->donViTinh),
            ])->values();
            $catalog = [
                'prices' => $prices,
                'services' => DichVu::query()
                    ->where(fn ($query) => $query->availableForBooking()->orWhereIn('DichVuID', $selectedServiceIds))
                    ->get(['DichVuID', 'TenDichVu', 'LoaiDichVuID'])
                    ->map(fn ($x) => ['id' => $x->DichVuID, 'name' => $x->TenDichVu, 'category_id' => $x->LoaiDichVuID]),
                'categories' => DB::table('LoaiDichVu')
                    ->where(fn ($query) => $query->where('TrangThai', 'Hoạt động')->orWhereIn('LoaiDichVuID', $selectedCategoryIds))
                    ->get()->map(fn ($x) => ['id' => $x->LoaiDichVuID, 'name' => $x->TenLoaiDichVu]),
                'garments' => LoaiDoGiat::query()
                    ->where(fn ($query) => $query->availableForBooking()->orWhereIn('LoaiDoGiatID', $selectedGarmentIds))
                    ->get(['LoaiDoGiatID', 'TenLoaiDoGiat'])
                    ->map(fn ($x) => ['id' => $x->LoaiDoGiatID, 'name' => $x->TenLoaiDoGiat]),
            ];
        }
        return [
            'id' => $booking->BookingID, 'code' => $booking->MaBooking, 'status' => $booking->TrangThai,
            'receive_method' => $booking->HinhThucNhanDo, 'receive_address' => $booking->DiaChiNhan,
            'return_method' => $booking->HinhThucTraDo, 'return_address' => $booking->DiaChiTra,
            'appointment_date' => $booking->NgayHen?->format('Y-m-d'), 'appointment_time' => $appointmentTime,
            'appointment' => $booking->NgayHen?->format('Y-m-d').' '.($appointmentTime ?? ''),
            'note' => $booking->GhiChu, 'created_at' => $booking->NgayTao?->toIso8601String(),
            'pickup_fee' => (float) $booking->PickupDeliveryFee, 'return_fee' => (float) $booking->DeliveryFee,
            'pickup_distance_meters' => (int) $booking->PickupDistanceMeters,
            'return_distance_meters' => (int) $booking->DeliveryDistanceMeters,
            'payment_method' => $booking->PhuongThucThanhToan,
            'customer' => ['id' => $booking->KhachHangID, 'name' => $booking->khachHang?->HoTen, 'phone' => $booking->khachHang?->SoDienThoai],
            'items' => $items, 'inspection_draft_saved' => $draft !== null,
            ...($includeCatalog ? [
                'available_points' => ($booking->khachHang?->points() ?? 0)
                    + ($booking->DiemDaTru ? (int) $booking->DiemSuDung : 0),
                'available_vouchers' => KhuyenMai::query()->where('TrangThai', 'Hoạt động')
                    ->whereDate('NgayBatDau', '<=', today())->whereDate('NgayKetThuc', '>=', today())
                    ->where(fn ($query) => $query->whereNull('SoLuongSuDung')->orWhere('SoLuongSuDung', '>', 0))
                    ->where(function ($query) use ($booking): void {
                        $query->whereNotExists(fn ($used) => $used->selectRaw('1')->from('Booking as used_booking')
                            ->whereColumn('used_booking.KhuyenMaiID', 'KhuyenMai.KhuyenMaiID')
                            ->where('used_booking.KhachHangID', $booking->KhachHangID)
                            ->where('used_booking.TrangThai', '<>', BookingStatus::Cancelled->value)
                            ->where('used_booking.BookingID', '<>', $booking->BookingID));
                        if ($booking->KhuyenMaiID) $query->orWhere('KhuyenMaiID', $booking->KhuyenMaiID);
                    })
                    ->orderBy('NgayKetThuc')->get(['KhuyenMaiID', 'MaKhuyenMai', 'TenKhuyenMai', 'LoaiKhuyenMai', 'GiaTriGiam', 'NgayKetThuc', 'GiaTriDonToiThieu', 'MucGiamToiDa', 'DieuKienApDung'])
                    ->map(fn (KhuyenMai $promotion) => [
                        'id' => $promotion->KhuyenMaiID, 'code' => $promotion->MaKhuyenMai,
                        'title' => $promotion->TenKhuyenMai, 'type' => $promotion->LoaiKhuyenMai,
                        'value' => $promotion->GiaTriGiam, 'expires_at' => $promotion->NgayKetThuc?->toDateString(),
                        'minimum' => $promotion->GiaTriDonToiThieu, 'maximum' => $promotion->MucGiamToiDa,
                        'condition' => $promotion->DieuKienApDung,
                    ])->values(),
                'selected_promotion_id' => array_key_exists('promotion_id', $draftPayload)
                    ? $draftPayload['promotion_id'] : $booking->KhuyenMaiID,
                'selected_points' => (int) ($draftPayload['points_used'] ?? $booking->DiemSuDung),
            ] : []),
            ...($catalog === null ? [] : ['catalog' => $catalog]),
        ];
    }

    private function customerCanUsePromotion(Booking $booking, int $promotionId): bool
    {
        return KhuyenMai::query()->whereKey($promotionId)->where('TrangThai', 'Hoạt động')
            ->whereDate('NgayBatDau', '<=', today())->whereDate('NgayKetThuc', '>=', today())
            ->where(fn ($query) => $query->whereNull('SoLuongSuDung')->orWhere('SoLuongSuDung', '>', 0))
            ->where(function ($query) use ($booking, $promotionId): void {
                $query->where('KhuyenMaiID', $booking->KhuyenMaiID)
                    ->orWhereNotExists(fn ($used) => $used->selectRaw('1')->from('Booking as used_booking')
                        ->whereColumn('used_booking.KhuyenMaiID', 'KhuyenMai.KhuyenMaiID')
                        ->where('used_booking.KhachHangID', $booking->KhachHangID)
                        ->where('used_booking.TrangThai', '<>', BookingStatus::Cancelled->value)
                        ->where('used_booking.BookingID', '<>', $booking->BookingID));
            })->whereKey($promotionId)->exists();
    }
}
