<?php

namespace App\Http\Controllers\Admin;

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
use App\Services\OrderService;
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
            'use_points' => ['required', 'boolean'],
        ]);

        return response()->json($orders->estimateBooking($record, $validated['items'], (bool) $validated['use_points']))
            ->header('Cache-Control', 'private, no-store');
    }

    public function inspection(int $id)
    {
        return $this->edit($id, true);
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
        if ($inspectionMode && ! $booking->isConvertibleToOrder()) {
            return redirect()->route('bookings.show', $booking)->with('error', 'Chỉ Booking chờ xác nhận mới có thể kiểm tra thực tế.');
        }

        $responsibleEmployeeLocked = $booking->donHangs->isNotEmpty();
        $employees = NhanVien::where('TrangThai', 'Hoạt động')->orderBy('HoTen')->get();
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
        $pricingUnitOptions = BangGia::query()
            ->with('donViTinh')
            ->where('TrangThai', 'Hoạt động')
            ->where(function ($query): void {
                $query->whereNull('NgayApDung')
                    ->orWhereDate('NgayApDung', '<=', today());
            })
            ->where(function ($query): void {
                $query->whereNull('NgayKetThuc')
                    ->orWhereDate('NgayKetThuc', '>=', today());
            })
            ->get()
            ->filter(fn (BangGia $pricing): bool => $pricing->donViTinh !== null)
            ->unique(fn (BangGia $pricing): string => implode(':', [
                $pricing->DichVuID,
                $pricing->LoaiDoGiatID,
                $pricing->DonViTinhID,
            ]))
            ->map(fn (BangGia $pricing): array => [
                'serviceId' => $pricing->DichVuID,
                'garmentId' => $pricing->LoaiDoGiatID,
                'unitId' => $pricing->DonViTinhID,
                'unit' => $pricing->unit,
                'label' => $pricing->donViTinh->TenDonViTinh.(
                    $pricing->donViTinh->KyHieu ? ' ('.$pricing->donViTinh->KyHieu.')' : ''
                ),
            ])
            ->values();

        return view('admin.bookings.edit', compact(
            'booking',
            'inspectionMode',
            'responsibleEmployeeLocked',
            'employees',
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
            $pointsToggleSubmitted = array_key_exists('use_points', $validated);
            $order = $this->bookingService->inspectBookingAndCreateOrder(
                $booking,
                (int) $validated['staff_id'],
                $validated['items'],
                $pointsToggleSubmitted ? 0 : (int) ($validated['DiemSuDung'] ?? 0),
                $pointsToggleSubmitted && (bool) $validated['use_points'],
                $validated,
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
