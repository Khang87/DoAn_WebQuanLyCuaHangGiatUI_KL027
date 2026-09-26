<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BookingRequest;
use App\Models\Customer;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Http\Request;

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

        $customers = Customer::orderBy('name')->get();
        $employees = User::where('role', '!=', 'customer')->orderBy('name')->get();

        return view('admin.bookings.index', compact('bookings', 'customers', 'employees'));
    }

    // create() and store() methods disabled - "Tạo đặt lịch" feature disabled

    public function show(int $id)
    {
        $booking = $this->bookingService->find($id);

        if (!$booking) {
            abort(404);
        }

        return view('admin.bookings.show', compact('booking'));
    }

    public function edit(int $id)
    {
        $booking = $this->bookingService->find($id);

        if (!$booking) {
            abort(404);
        }

        $customers = Customer::orderBy('name')->get();
        $employees = User::where('role', '!=', 'customer')->orderBy('name')->get();

        return view('admin.bookings.edit', compact('booking', 'customers', 'employees'));
    }

    public function update(BookingRequest $request, int $id)
    {
        $booking = $this->bookingService->find($id);

        if (!$booking) {
            abort(404);
        }

        try {
            $this->bookingService->update($booking, $request->validated());

            // Lịch hẹn vừa chuyển sang "Đã xác nhận" nên đã được sinh đơn tự động.
            $booking = $this->bookingService->find($id);
            $order = $booking?->order;

            if ($order) {
                return redirect()->route('bookings.index')->with(
                    'success',
                    'Đặt lịch ' . ($booking?->code ?? '') . ' đã được cập nhật và tự động tạo đơn hàng ' . $order->code . '.'
                );
            }

            return redirect()->route('bookings.index')->with('success', 'Đặt lịch đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('bookings.edit', $booking)->with('error', \App\Support\FriendlyError::message($e))->withInput();
        }
    }

    public function destroy(int $id)
    {
        $booking = $this->bookingService->find($id);

        if (!$booking) {
            abort(404);
        }

        try {
            $this->bookingService->delete($booking);

            return redirect()->route('bookings.index')->with('success', 'Đã xóa đặt lịch.');
        } catch (\Exception $e) {
            return redirect()->route('bookings.index')->with('error', \App\Support\FriendlyError::message($e));
        }
    }

    public function confirm(int $id)
    {
        $booking = $this->bookingService->find($id);

        if (!$booking) {
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
        } catch (\Exception $e) {
            return redirect()->route('bookings.index')->with('error', \App\Support\FriendlyError::message($e));
        }
    }
}
