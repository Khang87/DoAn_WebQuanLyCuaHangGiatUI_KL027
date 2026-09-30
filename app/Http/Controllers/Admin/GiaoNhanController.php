<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LuuGiaoNhanRequest;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\NhanVien;
use App\Services\DeliveryService;
use App\Support\FriendlyError;
use Illuminate\Http\Request;

class GiaoNhanController extends Controller
{
    public function __construct(
        private DeliveryService $deliveryService,
    ) {}

    public function index(Request $request)
    {
        $deliveries = $this->deliveryService->getAll([
            'search' => $request->input('search'),
            'customer_id' => $request->input('customer_id'),
            'method' => $request->input('method'),
            'status' => $request->input('status'),
            'sort_by' => $request->input('sort_by'),
            'sort_order' => $request->input('sort_order'),
        ]);

        $customers = KhachHang::orderBy('HoTen')->get();
        $employees = NhanVien::where('TrangThai', 'Hoạt động')->orderBy('HoTen')->get();

        return view('admin.deliveries.index', compact('deliveries', 'customers', 'employees'));
    }

    public function create()
    {
        $customers = KhachHang::orderBy('HoTen')->get();
        $employees = NhanVien::where('TrangThai', 'Hoạt động')->orderBy('HoTen')->get();
        $orders = DonHang::whereDoesntHave('giaoNhans')->where('TrangThai', '!=', 'Đã hủy')->orderBy('NgayTao', 'desc')->get();

        return view('admin.deliveries.create', compact('customers', 'employees', 'orders'));
    }

    public function store(LuuGiaoNhanRequest $request)
    {
        try {
            $delivery = $this->deliveryService->create($request->validated());

            return redirect()->route('deliveries.show', $delivery)->with('success', 'Giao nhận đã được tạo thành công.');
        } catch (\Exception $e) {
            return redirect()->route('deliveries.create')->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function show(int $id)
    {
        $delivery = $this->deliveryService->find($id);

        if (! $delivery) {
            abort(404);
        }

        return view('admin.deliveries.show', compact('delivery'));
    }

    public function edit(int $id)
    {
        $delivery = $this->deliveryService->find($id);

        if (! $delivery) {
            abort(404);
        }

        $customers = KhachHang::orderBy('HoTen')->get();
        $employees = NhanVien::where('TrangThai', 'Hoạt động')->orderBy('HoTen')->get();
        $orders = DonHang::whereDoesntHave('giaoNhans')->where('TrangThai', '!=', 'Đã hủy')->orderBy('NgayTao', 'desc')->get();

        return view('admin.deliveries.edit', compact('delivery', 'customers', 'employees', 'orders'));
    }

    public function update(LuuGiaoNhanRequest $request, int $id)
    {
        $delivery = $this->deliveryService->find($id);

        if (! $delivery) {
            abort(404);
        }

        try {
            $this->deliveryService->update($delivery, $request->validated());

            return redirect()->route('deliveries.index')->with('success', 'Giao nhận đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('deliveries.edit', $delivery)->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function destroy(int $id)
    {
        $delivery = $this->deliveryService->find($id);

        if (! $delivery) {
            abort(404);
        }

        try {
            $this->deliveryService->delete($delivery);

            return redirect()->route('deliveries.index')->with('success', 'Đã xóa giao nhận.');
        } catch (\Exception $e) {
            return redirect()->route('deliveries.index')->with('error', FriendlyError::message($e));
        }
    }
}
