<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LuuBookingRequest;
use App\Http\Requests\Admin\XacNhanBookingRequest;
use App\Models\BangGia;
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
        // Lấy nhân viên từ bảng NhanVien (không dùng User/TaiKhoan vì role là quan hệ many-to-many)
        $employees = NhanVien::where('TrangThai', 'Hoạt động')->orderBy('HoTen')->get();

        return view('admin.bookings.index', compact('bookings', 'customers', 'employees'));
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
            'items' => ['required', 'array', 'min:1', 'max:100'],
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

    public function quoteDeliveryFee(Request $request, int $booking, DeliveryFeeService $deliveryFeeService): JsonResponse
    {
        $record = $this->bookingService->find($booking);
        abort_if(! $record, 404);
        abort_if(! (config('giatui.booking_two_step')
            ? $record->statusEnum() === BookingStatus::Confirmed
            : $record->isConvertibleToOrder()), 409);

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
        abort_unless(config('giatui.booking_two_step'), 404);
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
        abort_unless(config('giatui.booking_two_step'), 404);
        $booking = $this->bookingService->find($id);
        abort_if(! $booking, 404);

        try {
            $this->bookingService->cancelPending($booking);

            return redirect()->route('bookings.show', $booking)->with('success', 'Đã hủy Booking.');
        } catch (ValidationException $e) {
            return redirect()->route('bookings.show', $booking)->withErrors($e->errors());
        }
    }

    public function markReceived(int $id)
    {
        abort_unless(config('giatui.booking_two_step'), 404);
        $booking = $this->bookingService->find($id);
        abort_if(! $booking, 404);

        try {
            $this->bookingService->markReceived($booking);

            return redirect()->route('bookings.show', $booking)->with('success', 'Đã ghi nhận nhận đồ thực tế. Có thể bắt đầu kiểm kê.');
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
        if ($inspectionMode && ! (config('giatui.booking_two_step')
            ? $booking->statusEnum() === BookingStatus::Confirmed
            : $booking->isConvertibleToOrder())) {
            return redirect()->route('bookings.show', $booking)->with('error', 'Booking chưa ở trạng thái cho phép kiểm kê.');
        }
        if ($inspectionMode && config('giatui.booking_two_step')
            && $booking->giaoNhanDuKiens->firstWhere('LoaiGiaoNhan', 'NHAN_DO')?->TrangThai !== 'Đã nhận') {
            return redirect()->route('bookings.show', $booking)->with('error', 'Hãy ghi nhận nhận đồ trước khi kiểm kê.');
        }

        $authenticatedEmployee = NhanVien::query()
            ->whereKey(request()->user()?->NhanVienID)
            ->where('TrangThai', 'Hoạt động')
            ->first(['NhanVienID', 'HoTen']);
        $serviceIds = $booking->chiTietBookings->pluck('DichVuID')->all();
        $garmentIds = $booking->chiTietBookings->pluck('LoaiDoGiatID')->all();
        $unitIds = $booking->chiTietBookings->pluck('DonViTinhID')->all();
        $services = DichVu::query()
            ->where(fn ($query) => $query->where('TrangThai', 'Hoạt động')->orWhereIn('DichVuID', $serviceIds))
            ->orderBy('TenDichVu')
            ->get();
        $serviceOptions = $services->map(fn (DichVu $service): array => [
            'id' => $service->DichVuID,
            'name' => $service->TenDichVu,
            'categoryId' => $service->LoaiDichVuID,
        ])->values();
        $serviceCategories = LoaiDichVu::query()
            ->orderBy('TenLoaiDichVu')
            ->get(['LoaiDichVuID', 'TenLoaiDichVu']);
        $garments = LoaiDoGiat::query()
            ->where(fn ($query) => $query->where('TrangThai', 'Hoạt động')->orWhereIn('LoaiDoGiatID', $garmentIds))
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
        $pricingUnitOptions = $this->pricingService->orderOptions()
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

        return view('admin.bookings.edit', compact(
            'booking',
            'inspectionMode',
            'authenticatedEmployee',
            'services',
            'serviceOptions',
            'serviceCategories',
            'garments',
            'garmentOptions',
            'units',
            'pricingUnitOptions',
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
            if (config('giatui.booking_two_step')) {
                $order = $this->bookingService->submitInspection($booking, $validated);

                return redirect()->route('orders.show', $order)->with('success', 'Đã đối soát kiểm kê và tạo đơn '.$order->MaDonHang.'.');
            }
            $order = $this->bookingService->inspectBookingAndCreateOrder(
                $booking,
                $request->user()?->NhanVienID,
                $validated['items'],
                0,
                false,
                $validated,
                (bool) ($validated['recalculate_delivery_fee'] ?? false),
                true,
            );

            return redirect()->route('orders.show', $order)->with(
                'success',
                'Đã tiếp nhận Booking và tạo đơn hàng '.$order->MaDonHang.' ở trạng thái Đã tiếp nhận.',
            );
        } catch (ValidationException $e) {
            return redirect()->route('bookings.inspection', $booking)->withErrors($e->errors())->withInput();
        } catch (Throwable $e) {
            $this->logBookingFailure($booking->BookingID, 'confirm', $e);

            return redirect()->route('bookings.index')->with('error', FriendlyError::message($e));
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
