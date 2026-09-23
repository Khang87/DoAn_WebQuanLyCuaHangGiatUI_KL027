<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BookingRequest;
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
            'customer_id' => $request->input('customer_id'),
            'status' => $request->input('status'),
            'delivery_method' => $request->input('delivery_method'),
        ]);

        $customers = \App\Models\Customer::orderBy('name')->get();
        $services = \App\Models\Service::where('status', 'active')->orderBy('name')->get();

        return view('admin.bookings.index', compact('bookings', 'customers', 'services'));
    }

    public function create()
    {
        $customers = \App\Models\Customer::orderBy('name')->get();
        $services = \App\Models\Service::where('status', 'active')->orderBy('name')->get();

        return view('admin.bookings.create', compact('customers', 'services'));
    }

    public function store(BookingRequest $request)
    {
        try {
            $this->bookingService->create($request->validated());

            return redirect()->route('bookings.index')->with('success', 'Đặt lịch đã được tạo.');
        } catch (\Exception $e) {
            return redirect()->route('bookings.create')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

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

        $customers = \App\Models\Customer::orderBy('name')->get();
        $services = \App\Models\Service::where('status', 'active')->orderBy('name')->get();

        return view('admin.bookings.edit', compact('booking', 'customers', 'services'));
    }

    public function update(BookingRequest $request, int $id)
    {
        $booking = $this->bookingService->find($id);

        if (!$booking) {
            abort(404);
        }

        try {
            $this->bookingService->update($booking, $request->validated());

            return redirect()->route('bookings.index')->with('success', 'Đặt lịch đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('bookings.edit', $booking)->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
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
            return redirect()->route('bookings.index')->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }
}
