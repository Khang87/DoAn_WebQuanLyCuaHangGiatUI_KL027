<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Exceptions\SettledOrderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LuuGiaoNhanRequest;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\NhanVien;
use App\Services\DeliveryService;
use App\Support\FriendlyError;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

        $customers = KhachHang::query()->orderBy('HoTen')->get(['KhachHangID', 'HoTen']);
        $employees = NhanVien::where('TrangThai', 'Hoạt động')
            ->orderBy('HoTen')
            ->get(['NhanVienID', 'HoTen']);

        return view('admin.deliveries.index', compact('deliveries', 'customers', 'employees'));
    }

    public function create()
    {
        $customers = KhachHang::query()->orderBy('HoTen')->get(['KhachHangID', 'HoTen']);
        $employees = NhanVien::where('TrangThai', 'Hoạt động')
            ->orderBy('HoTen')
            ->get(['NhanVienID', 'HoTen']);
        $orders = $this->orderOptions();

        return view('admin.deliveries.create', compact('customers', 'employees', 'orders'));
    }

    public function store(LuuGiaoNhanRequest $request)
    {
        try {
            $delivery = $this->deliveryService->create($request->validated());

            return redirect()->route('deliveries.show', $delivery)->with('success', 'Giao nhận đã được tạo thành công.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (SettledOrderException $e) {
            return back()->with('error', $e->getMessage())->withInput();
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

        $customers = KhachHang::query()->orderBy('HoTen')->get(['KhachHangID', 'HoTen']);
        $employees = NhanVien::where(fn ($query) => $query->where('TrangThai', 'Hoạt động')->orWhere('NhanVienID', $delivery->NhanVienID))
            ->orderBy('HoTen')
            ->get(['NhanVienID', 'HoTen']);
        $orders = $this->orderOptions($delivery->DonHangID);

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
        } catch (ValidationException $e) {
            throw $e;
        } catch (SettledOrderException $e) {
            return back()->with('error', $e->getMessage())->withInput();
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
            if (! $this->deliveryService->delete($delivery)) {
                return redirect()->route('deliveries.index')->with('error', 'Không thể xóa giao nhận. Vui lòng thử lại.');
            }

            return redirect()->route('deliveries.index')->with('success', 'Đã xóa giao nhận.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (SettledOrderException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        } catch (\Exception $e) {
            return redirect()->route('deliveries.index')->with('error', FriendlyError::message($e));
        }
    }

    /**
     * @return Collection<int, DonHang>
     */
    private function orderOptions(?int $currentOrderId = null): Collection
    {
        return DonHang::query()
            ->select(['DonHangID', 'MaDonHang', 'KhachHangID', 'NgayTao'])
            ->with('khachHang:KhachHangID,HoTen')
            ->whereNotIn('TrangThai', [OrderStatus::Cancelled->value, OrderStatus::Paid->value])
            ->where(function ($query) use ($currentOrderId): void {
                foreach (['NHAN_DO', 'GIAO_DO'] as $type) {
                    $query->orWhereDoesntHave('giaoNhans', fn ($leg) => $leg
                        ->where('LoaiGiaoNhan', $type)->where('TrangThai', '!=', 'Đã hủy'));
                }
                if ($currentOrderId !== null) {
                    $query->orWhereKey($currentOrderId);
                }
            })
            ->orderByDesc('NgayTao')
            ->get();
    }
}
