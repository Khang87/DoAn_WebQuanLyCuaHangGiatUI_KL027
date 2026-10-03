<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LuuBookingRequest;
use App\Http\Requests\Admin\XacNhanBookingRequest;
use App\Models\DichVu;
use App\Models\DonViTinh;
use App\Models\KhachHang;
use App\Models\LoaiDoGiat;
use App\Models\NhanVien;
use App\Services\BookingService;
use App\Support\FriendlyError;
use Illuminate\Database\QueryException;
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

    public function show(int $id)
    {
        $booking = $this->bookingService->find($id);

        if (! $booking) {
            abort(404);
        }

        $employees = NhanVien::query()->orderBy('HoTen')->get(['NhanVienID', 'HoTen']);

        return view('admin.bookings.show', compact('booking', 'employees'));
    }

    public function edit(int $id)
    {
        $booking = $this->bookingService->find($id);

        if (! $booking) {
            abort(404);
        }

        $customers = KhachHang::orderBy('HoTen')->get();
        $employees = NhanVien::where('TrangThai', 'Hoạt động')->orderBy('HoTen')->get();
        $serviceIds = $booking->chiTietBookings->pluck('DichVuID')->all();
        $garmentIds = $booking->chiTietBookings->pluck('LoaiDoGiatID')->all();
        $unitIds = $booking->chiTietBookings->pluck('DonViTinhID')->all();
        $services = DichVu::query()
            ->where(fn ($query) => $query->where('TrangThai', 'Hoạt động')->orWhereIn('DichVuID', $serviceIds))
            ->orderBy('TenDichVu')
            ->get();
        $garments = LoaiDoGiat::query()
            ->where(fn ($query) => $query->where('TrangThai', 'Hoạt động')->orWhereIn('LoaiDoGiatID', $garmentIds))
            ->orderBy('TenLoaiDoGiat')
            ->get();
        $units = DonViTinh::query()
            ->where(fn ($query) => $query->where('TrangThai', 'Hoạt động')->orWhereIn('DonViTinhID', $unitIds))
            ->orderBy('TenDonViTinh')
            ->get();

        return view('admin.bookings.edit', compact('booking', 'customers', 'employees', 'services', 'garments', 'units'));
    }

    public function update(LuuBookingRequest $request, int $id)
    {
        $booking = $this->bookingService->find($id);

        if (! $booking) {
            abort(404);
        }

        try {
            $this->bookingService->update($booking, $request->validated());

            $booking = $this->bookingService->find($id);

            $order = $booking?->donHangs()->orderByDesc('DonHangID')->first();

            if ($order) {
                return redirect()->route('bookings.index')->with(
                    'success',
                    'Đặt lịch '.($booking?->MaBooking ?? '').' đã được cập nhật và tự động tạo đơn hàng '.$order->MaDonHang.'.'
                );
            }

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
            $order = $this->bookingService->confirmPendingBooking(
                $booking,
                (int) $request->validated()['NhanVienID'],
                (int) ($request->validated()['DiemSuDung'] ?? 0),
            );

            return redirect()->route('orders.show', $order)->with(
                'success',
                'Đặt lịch đã được xác nhận và tạo đơn hàng '.$order->MaDonHang.' thành công.',
            );
        } catch (ValidationException $e) {
            return redirect()->route('bookings.edit', $booking)->withErrors($e->errors())->withInput();
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
