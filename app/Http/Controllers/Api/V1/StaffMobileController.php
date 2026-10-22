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
use App\Models\StaffBookingDraft;
use App\Models\User;
use App\Services\BookingService;
use App\Services\DeliveryService;
use App\Services\DeliveryFeeService;
use App\Services\OrderService;
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

    public function bookings(): JsonResponse
    {
        $employeeId = Auth::user()->NhanVienID;
        $rows = Booking::query()->where('TrangThai', BookingStatus::Confirmed->value)->where('HinhThucNhanDo', 'Tại nhà')
            ->whereHas('giaoNhanDuKiens', fn ($q) => $q->where('LoaiGiaoNhan', 'NHAN_DO')->where('NhanVienID', $employeeId)
                ->whereIn('TrangThai', [DeliveryStatus::Pending->dbValue(), DeliveryStatus::Picking->dbValue()]))
            ->with(['khachHang', 'chiTietBookings.dichVu', 'chiTietBookings.loaiDoGiat', 'chiTietBookings.donViTinh', 'giaoNhanDuKiens'])
            ->latest('NgayTao')->get()->map(fn (Booking $b) => $this->bookingData($b));
        return response()->json(['data' => $rows]);
    }

    public function orders(): JsonResponse
    {
        $employeeId = Auth::user()->NhanVienID;
        $rows = DonHang::query()->where(function ($query) use ($employeeId): void {
            $query->where('NhanVienID', $employeeId)
                ->orWhereHas('giaoNhans', fn ($delivery) => $delivery->where('NhanVienID', $employeeId)->where('LoaiGiaoNhan', 'GIAO_DO'))
                ->orWhereHas('booking.giaoNhanDuKiens', fn ($delivery) => $delivery->where('NhanVienID', $employeeId)->where('LoaiGiaoNhan', 'GIAO_DO'));
        })
            ->with([
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
        $order = DonHang::query()->where(function ($query) use ($employeeId): void {
            $query->where('NhanVienID', $employeeId)
                ->orWhereHas('giaoNhans', fn ($delivery) => $delivery->where('NhanVienID', $employeeId)->where('LoaiGiaoNhan', 'GIAO_DO'))
                ->orWhereHas('booking.giaoNhanDuKiens', fn ($delivery) => $delivery->where('NhanVienID', $employeeId)->where('LoaiGiaoNhan', 'GIAO_DO'));
        })->findOrFail($id);
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
        return response()->json(['data' => $this->orders->estimateBooking($booking, $data['items'])]);
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

    private function validatedInspection(Request $request): array
    {
        return $request->validate([
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
        ]);
    }

    private function bookingData(Booking $booking): array
    {
        $draft = StaffBookingDraft::query()->find($booking->BookingID);
        $draftItems = array_values($draft?->Payload['items'] ?? []);
        $items = $booking->chiTietBookings->values()->map(fn ($i, $index) => [
            'service_id' => $i->DichVuID, 'garment_id' => $i->LoaiDoGiatID, 'unit_id' => $i->DonViTinhID,
            'quantity' => $i->SoLuong === null ? null : (float) $i->SoLuong, 'weight' => $i->KhoiLuong === null ? null : (float) $i->KhoiLuong,
            'unit_price' => (float) $i->DonGia, 'subtotal' => (float) $i->ThanhTien,
            'service_name' => $i->dichVu?->TenDichVu, 'garment_name' => $i->loaiDoGiat?->TenLoaiDoGiat,
            'unit_name' => $i->donViTinh?->KyHieu ?: $i->donViTinh?->TenDonViTinh,
            'condition' => $draftItems[$index]['TinhTrangTruocKhiGiat'] ?? '', 'note' => $i->GhiChu,
        ])->values();
        $prices = BangGia::query()->where('TrangThai', 'Hoạt động')->with(['dichVu', 'loaiDoGiat', 'donViTinh'])->get()->map(fn ($p) => [
            'service_id' => $p->DichVuID, 'garment_id' => $p->LoaiDoGiatID, 'unit_id' => $p->DonViTinhID,
            'unit_price' => (float) $p->DonGia, 'is_weight' => BangGia::isWeightUnit($p->donViTinh),
        ])->values();
        return [
            'id' => $booking->BookingID, 'code' => $booking->MaBooking, 'status' => $booking->TrangThai,
            'receive_method' => $booking->HinhThucNhanDo, 'receive_address' => $booking->DiaChiNhan,
            'return_method' => $booking->HinhThucTraDo, 'return_address' => $booking->DiaChiTra,
            'appointment_date' => $booking->NgayHen?->format('Y-m-d'), 'appointment_time' => substr((string) $booking->GioHen, 0, 5),
            'appointment' => $booking->NgayHen?->format('Y-m-d').' '.substr((string) $booking->GioHen, 0, 5),
            'note' => $booking->GhiChu, 'created_at' => $booking->NgayTao?->toIso8601String(),
            'pickup_fee' => (float) $booking->PickupDeliveryFee, 'return_fee' => (float) $booking->DeliveryFee,
            'customer' => ['id' => $booking->KhachHangID, 'name' => $booking->khachHang?->HoTen, 'phone' => $booking->khachHang?->SoDienThoai],
            'items' => $items, 'inspection_draft_saved' => $draft !== null,
            'available_points' => $booking->khachHang?->points() ?? 0,
            'catalog' => ['prices' => $prices,
                'services' => DichVu::query()->where('TrangThai', 'Hoạt động')->get(['DichVuID', 'TenDichVu', 'LoaiDichVuID'])->map(fn ($x) => ['id' => $x->DichVuID, 'name' => $x->TenDichVu, 'category_id' => $x->LoaiDichVuID]),
                'categories' => DB::table('LoaiDichVu')->where('TrangThai', 'Hoạt động')->get()->map(fn ($x) => ['id' => $x->LoaiDichVuID, 'name' => $x->TenLoaiDichVu]),
                'garments' => LoaiDoGiat::query()->where('TrangThai', 'Hoạt động')->get()->map(fn ($x) => ['id' => $x->LoaiDoGiatID, 'name' => $x->TenLoaiDoGiat])],
        ];
    }
}
