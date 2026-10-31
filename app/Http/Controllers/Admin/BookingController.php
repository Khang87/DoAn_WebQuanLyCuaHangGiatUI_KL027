<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BookingQuoteRefusalRequest;
use App\Http\Requests\Admin\LuuBookingRequest;
use App\Http\Requests\Admin\XacNhanBookingRequest;
use App\Models\BangGia;
use App\Models\Booking;
use App\Models\KhuyenMai;
use App\Models\DichVu;
use App\Models\DonViTinh;
use App\Models\KhachHang;
use App\Models\LoaiDichVu;
use App\Models\LoaiDoGiat;
use App\Models\NhanVien;
use App\Services\BookingService;
use App\Services\DeliveryFeeService;
use App\Services\OrderService;
use App\Services\PricingService;
use App\Support\FriendlyError;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PDOException;
use Throwable;

class BookingController extends Controller
{
    public function __construct(
        private BookingService $bookingService,
        private PricingService $pricingService,
    ) {}

    public function index(Request $request)
    {
        $bookings = $this->bookingService->getAll([
            'search' => $request->input('search'),
            'customer_id' => $request->input('customer_id'),
            'method' => $request->input('method'),
            'return_method' => $request->input('return_method'),
            'status' => $request->input('status'),
            'sort_by' => $request->input('sort_by'),
            'sort_order' => $request->input('sort_order'),
        ]);

        $customers = KhachHang::orderBy('HoTen')->get();
        return view('admin.bookings.index', compact('bookings', 'customers'));
    }

    // create() and store() methods disabled - "Tạo đặt lịch" feature disabled

    public function show(int $id, OrderService $orders)
    {
        $booking = $this->bookingService->find($id);

        if (! $booking) {
            abort(404);
        }

        $employees = NhanVien::query()->orderBy('HoTen')->get(['NhanVienID', 'HoTen']);
        $bookingAmounts = $booking->chiTietBookings->isNotEmpty() ? $orders->estimateSavedBooking($booking) : null;

        return view('admin.bookings.show', compact('booking', 'employees', 'bookingAmounts'));
    }

    public function estimate(Request $request, int $booking, OrderService $orders): JsonResponse
    {
        $record = $this->bookingService->find($booking);
        abort_if(! $record, 404);
        $validated = $request->validate([
            'items' => ['present', 'array', 'max:100'],
            'items.*.DichVuID' => ['required', 'integer', 'min:1'],
            'items.*.LoaiDoGiatID' => ['required', 'integer', 'min:1'],
            'items.*.DonViTinhID' => ['required', 'integer', 'min:1'],
            'items.*.SoLuong' => ['nullable', 'numeric', 'min:0'],
            'items.*.KhoiLuong' => ['nullable', 'numeric', 'min:0'],
            'method' => ['sometimes', 'required', 'in:Tại nhà,Tại cửa hàng'],
            'return_method' => ['sometimes', 'required', 'in:Tại nhà,Tại cửa hàng'],
        ]);

        // Preview selected methods without persisting or accepting client-supplied fees.
        $record->fill(array_filter([
            'HinhThucNhanDo' => $validated['method'] ?? null,
            'HinhThucTraDo' => $validated['return_method'] ?? null,
        ], fn ($value) => $value !== null));

        return response()->json($orders->estimateBooking($record, $validated['items']))
            ->header('Cache-Control', 'private, no-store');
    }

    public function applyCoupon(Request $request, int $booking)
    {
        $record = $this->bookingService->find($booking);
        abort_if(! $record, 404);

        $validated = $request->validate([
            'ma_khuyen_mai' => ['required', 'string', 'max:100'],
        ]);

        $code = trim($validated['ma_khuyen_mai']);
        $khuyenMai = KhuyenMai::findByCode($code);

        if (! $khuyenMai) {
            return back()->withErrors(['ma_khuyen_mai' => 'Mã giảm giá không tồn tại.'])->withInput();
        }

        if (! $khuyenMai->isValid()) {
            return back()->withErrors(['ma_khuyen_mai' => 'Mã giảm giá không còn hiệu lực.'])->withInput();
        }

        $customer = $record->khachHang;

        if ($record->chiTietBookings->isNotEmpty()) {
            $subtotal = (float) $record->chiTietBookings->sum(fn ($item) => (float) ($item->ThanhTien ?? 0));
            if (! $khuyenMai->isApplicableForCustomer($customer, $subtotal)) {
                return back()->withErrors(['ma_khuyen_mai' => $khuyenMai->rejectionReasonForCustomer($customer, $subtotal)])->withInput();
            }
        }

        try {
            $record->update([
                'KhuyenMaiID' => $khuyenMai->KhuyenMaiID,
                'KhuyenMaiDaTru' => true,
                'NgayCapNhat' => now(),
            ]);

            return redirect()
                ->route('bookings.show', $record)
                ->with('success', 'Đã áp dụng mã giảm giá '.$khuyenMai->MaKhuyenMai.'.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Không thể áp dụng mã giảm giá.')->withInput();
        }
    }

    public function quoteDeliveryFee(Request $request, int $booking, DeliveryFeeService $deliveryFeeService): JsonResponse
    {
        $record = $this->bookingService->find($booking);
        abort_if(! $record, 404);
        abort_if($record->statusEnum() !== BookingStatus::Confirmed, 409);
        abort_unless(in_array(
            $record->giaoNhanDuKiens->firstWhere('LoaiGiaoNhan', 'NHAN_DO')?->TrangThai,
            [DeliveryStatus::Pending->dbValue(), DeliveryStatus::Picking->dbValue()],
            true,
        ), 409);

        $validated = $request->validate([
            'method' => ['required', 'in:Tại nhà,Tại cửa hàng'],
            'address' => ['nullable', 'string', 'max:255', 'required_if:method,Tại nhà'],
            'return_method' => ['required', 'in:Tại nhà,Tại cửa hàng'],
            'return_address' => ['nullable', 'string', 'max:255', 'required_if:return_method,Tại nhà'],
        ]);
        $quote = $deliveryFeeService->quote(
            $validated['method'],
            $validated['address'] ?? null,
            $validated['return_method'],
            $validated['return_address'] ?? null,
        );

        return response()->json($quote)->header('Cache-Control', 'private, no-store');
    }

    public function inspection(int $id)
    {
        return $this->edit($id, true);
    }

    public function approve(int $id)
    {
        $booking = $this->bookingService->find($id);
        abort_if(! $booking, 404);

        try {
            $this->bookingService->approve($booking);

            return redirect()->route('bookings.show', $booking)->with('success', 'Đã xác nhận Booking và lập phiếu nhận, phiếu trả. Chưa tạo đơn hàng.');
        } catch (ValidationException $e) {
            return redirect()->route('bookings.show', $booking)->withErrors($e->errors());
        }
    }

    public function cancelPending(int $id)
    {
        $booking = $this->bookingService->find($id);
        abort_if(! $booking, 404);

        try {
            $this->bookingService->cancelPending($booking);

            return redirect()->route('bookings.show', $booking)->with('success', 'Đã hủy Booking.');
        } catch (ValidationException $e) {
            return redirect()->route('bookings.show', $booking)->withErrors($e->errors());
        }
    }

    public function edit(int $id, bool $inspectionMode = false)
    {
        $booking = $this->bookingService->find($id);

        if (! $booking) {
            abort(404);
        }

        if ($inspectionMode && $booking->donHangs->isNotEmpty()) {
            return redirect()->route('orders.show', $booking->donHangs->first());
        }
        if ($inspectionMode) {
            try {
                $this->bookingService->assertCanInspect($booking);
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())->flatten()->first() ?? $exception->getMessage();

                return redirect()->route('bookings.show', $booking)->with('error', $message);
            }
        }

        $serviceIds = $booking->chiTietBookings->pluck('DichVuID')
            ->merge($booking->requestedServices->pluck('DichVuID'))
            ->unique()->all();
        $garmentIds = $booking->chiTietBookings->pluck('LoaiDoGiatID')
            ->merge($booking->requestedServices->pluck('LoaiDoGiatID'))
            ->unique()->all();
        $unitIds = $booking->chiTietBookings->pluck('DonViTinhID')
            ->merge($booking->requestedServices->pluck('DonViTinhID'))
            ->unique()->all();
        $services = DichVu::query()
            ->where(fn ($query) => $query->availableForBooking()->orWhereIn('DichVuID', $serviceIds))
            ->orderBy('TenDichVu')
            ->get();
        $serviceOptions = $services->map(fn (DichVu $service): array => [
            'id' => $service->DichVuID,
            'name' => $service->TenDichVu,
            'categoryId' => $service->LoaiDichVuID,
        ])->values();
        $serviceCategories = LoaiDichVu::query()
            ->where(fn ($query) => $query->where('TrangThai', 'Hoạt động')
                ->orWhereIn('LoaiDichVuID', $services->pluck('LoaiDichVuID')))
            ->orderBy('TenLoaiDichVu')
            ->get(['LoaiDichVuID', 'TenLoaiDichVu']);
        $garments = LoaiDoGiat::query()
            ->where(fn ($query) => $query->availableForBooking()->orWhereIn('LoaiDoGiatID', $garmentIds))
            ->orderBy('TenLoaiDoGiat')
            ->get();
        $garmentOptions = $garments->map(fn (LoaiDoGiat $garment): array => [
            'id' => $garment->LoaiDoGiatID,
            'name' => $garment->TenLoaiDoGiat,
        ])->values();
        $units = DonViTinh::query()
            ->where(fn ($query) => $query->where('TrangThai', 'Hoạt động')->orWhereIn('DonViTinhID', $unitIds))
            ->orderBy('TenDonViTinh')
            ->get();
        $pricingUnitOptions = $this->pricingService->orderOptionsForRecord($booking)
            ->map(fn (BangGia $pricing): array => [
                'serviceId' => $pricing->DichVuID,
                'garmentId' => $pricing->LoaiDoGiatID,
                'unitId' => $pricing->DonViTinhID,
                'unit' => $pricing->unit,
                'price' => (float) $pricing->DonGia,
                'label' => $pricing->donViTinh->TenDonViTinh.(
                    $pricing->donViTinh->KyHieu ? ' ('.$pricing->donViTinh->KyHieu.')' : ''
                ),
            ])
            ->values();

        $usedPromotionIds = Booking::query()
            ->where('KhachHangID', $booking->KhachHangID)
            ->whereKeyNot($booking->BookingID)
            ->where('TrangThai', '<>', BookingStatus::Cancelled->value)
            ->whereNotNull('KhuyenMaiID')
            ->pluck('KhuyenMaiID');
        $currentCouponSubtotal = (float) $booking->chiTietBookings->sum('ThanhTien');

        // Chỉ gửi các mã còn hiệu lực, còn lượt và chưa được khách dùng ở Booking khác.
        $coupons = KhuyenMai::query()->where('TrangThai', 'Hoạt động')
            ->where('NgayBatDau', '<=', today())
            ->where('NgayKetThuc', '>=', today())
            ->where(function ($q) use ($booking) {
                $q->whereNull('SoLuongSuDung')->orWhere('SoLuongSuDung', '>', 0);
                if ($booking->KhuyenMaiID !== null) {
                    $q->orWhereKey($booking->KhuyenMaiID);
                }
            })
            ->whereNotIn('KhuyenMaiID', $usedPromotionIds)
            ->orderBy('MaKhuyenMai')
            ->get()
            ->filter(fn (KhuyenMai $coupon): bool => $coupon->rejectionReasonForCustomer(
                $booking->khachHang,
                PHP_FLOAT_MAX,
            ) === null)
            ->values();
        $minimumPromotionThreshold = null;
        if (! $inspectionMode) {
            $minimumPromotionThreshold = $coupons
                ->filter(fn (KhuyenMai $coupon): bool => $coupon->GiaTriDonToiThieu !== null
                    && $currentCouponSubtotal < (float) $coupon->GiaTriDonToiThieu)
                ->min(fn (KhuyenMai $coupon): float => (float) $coupon->GiaTriDonToiThieu);
            $coupons = $coupons->filter(fn (KhuyenMai $coupon): bool =>
                $coupon->GiaTriDonToiThieu === null
                || $currentCouponSubtotal >= (float) $coupon->GiaTriDonToiThieu
            )->values();
        }

        return view('admin.bookings.edit', compact(
            'booking',
            'inspectionMode',
            'services',
            'serviceOptions',
            'serviceCategories',
            'garments',
            'garmentOptions',
            'units',
            'pricingUnitOptions',
            'coupons',
            'currentCouponSubtotal',
            'minimumPromotionThreshold',
        ));
    }

    public function update(LuuBookingRequest $request, int $id)
    {
        $booking = $this->bookingService->find($id);

        if (! $booking) {
            abort(404);
        }

        try {
            $this->bookingService->update($booking, $request->validated());

            return redirect()->route('bookings.index')->with('success', 'Đặt lịch đã được cập nhật.');
        } catch (ValidationException $e) {
            return redirect()->route('bookings.edit', $booking)->withErrors($e->errors())->withInput();
        } catch (Throwable $e) {
            $this->logBookingFailure($booking->BookingID, 'update', $e);

            return redirect()->route('bookings.edit', $booking)->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function destroy(int $id)
    {
        $booking = $this->bookingService->find($id);

        if (! $booking) {
            abort(404);
        }

        try {
            $deleted = $this->bookingService->delete($booking);
            if (! $deleted) {
                return redirect()->route('bookings.index')->with(
                    'error',
                    'Không thể xóa đặt lịch vì đã có đơn hàng liên kết. Hãy xử lý đơn hàng trước.',
                );
            }

            return redirect()->route('bookings.index')->with('success', 'Đã xóa đặt lịch.');
        } catch (Throwable $e) {
            return redirect()->route('bookings.index')->with('error', FriendlyError::message($e));
        }
    }

    public function confirm(XacNhanBookingRequest $request, int $id)
    {
        $booking = $this->bookingService->find($id);

        if (! $booking) {
            abort(404);
        }

        try {
            $validated = $request->validated();
            $order = $this->bookingService->submitInspection($booking, $validated);

            return redirect()->route('orders.show', $order)->with('success', 'Đã đối soát kiểm kê và tạo đơn '.$order->MaDonHang.'.');
        } catch (ValidationException $e) {
            return redirect()->route('bookings.inspection', $booking)->withErrors($e->errors())->withInput();
        } catch (Throwable $e) {
            $this->logBookingFailure($booking->BookingID, 'confirm', $e);

            return redirect()->route('bookings.index')->with('error', FriendlyError::message($e));
        }
    }

    public function refuseQuote(BookingQuoteRefusalRequest $request, int $id)
    {
        $booking = $this->bookingService->find($id);

        if (! $booking) {
            abort(404);
        }

        try {
            $this->bookingService->refuseQuote($booking, $request->validated());

            return redirect()->route('bookings.show', $booking)
                ->with('success', 'Đã hủy Booking theo yêu cầu từ chối báo giá. Không tạo đơn và không thu phí nhận đồ.');
        } catch (ValidationException $exception) {
            return redirect()->route('bookings.inspection', $booking)
                ->withErrors($exception->errors())
                ->withInput();
        } catch (Throwable $exception) {
            $this->logBookingFailure($booking->BookingID, 'refuse_quote', $exception);

            return redirect()->route('bookings.inspection', $booking)
                ->with('error', FriendlyError::message($exception))
                ->withInput();
        }
    }

    private function logBookingFailure(int $bookingId, string $operation, Throwable $exception): void
    {
        Log::error('Booking operation failed', [
            'booking_id' => $bookingId,
            'operation' => $operation,
            'exception' => $exception::class,
            'sql_state' => FriendlyError::sqlState($exception),
            'database_connection' => $exception instanceof QueryException
                ? $exception->connectionName
                : null,
            'database_error' => $this->databaseErrorDetail($exception),
        ]);
    }

    private function databaseErrorDetail(Throwable $exception): ?string
    {
        for ($cause = $exception; $cause !== null; $cause = $cause->getPrevious()) {
            if (! $cause instanceof PDOException) {
                continue;
            }

            $message = preg_replace('/[\r\n\t]+/u', ' ', $cause->getMessage()) ?? '';
            $detail = preg_match('/ERROR:\s*(.*?)(?:\s+CONTEXT:|\s+\(Connection:|$)/i', $message, $matches) === 1
                ? 'PostgreSQL ERROR: '.$matches[1]
                : 'PostgreSQL operation failed.';

            return mb_substr($detail, 0, 1000);
        }

        return null;
    }
}
