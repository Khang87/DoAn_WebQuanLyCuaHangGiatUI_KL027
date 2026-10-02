<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LuuBookingRequest;
use App\Models\DichVu;
use App\Models\DonViTinh;
use App\Models\KhachHang;
use App\Models\LoaiDoGiat;
use App\Models\NhanVien;
use App\Services\BookingService;
use App\Support\FriendlyError;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

        return view('admin.bookings.show', compact('booking'));
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
            if ($booking?->isConvertibleToOrder()) {
                $this->bookingService->confirmAndCreateOrder($booking);
                $booking = $this->bookingService->find($id);
            }

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
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
            return redirect()->route('bookings.index')->with('error', FriendlyError::message($e));
        }
    }

    public function confirm(int $id)
    {
        $booking = $this->bookingService->find($id);

        if (! $booking) {
            abort(404);
        }

        try {
            // Đã có đơn rồi thì không tạo lại, chỉ đưa người dùng tới đơn cũ.
            if ($this->bookingService->hasConvertedOrder($booking)) {
                $order = $this->bookingService->confirmAndCreateOrder($booking);

                return redirect()->route('orders.show', $order)
                    ->with('success', 'Đặt lịch này đã được chuyển thành đơn hàng trước đó.');
            }

            $order = $this->bookingService->confirmAndCreateOrder($booking);

            if ($order) {
                return redirect()->route('orders.show', $order)->with('success', 'Đặt lịch đã được xác nhận và tạo đơn hàng thành công.');
            }

            return redirect()->route('bookings.index')->with('error', 'Chỉ đặt lịch đã xác nhận mới chuyển thành đơn hàng được.');
        } catch (ValidationException $e) {
            return redirect()->route('bookings.edit', $booking)->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            return redirect()->route('bookings.index')->with('error', FriendlyError::message($e));
        }
    }
}
