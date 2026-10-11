<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeliveryStatus;
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
        $selectedOrderId = request()->integer('order_id') ?: null;
        $selectedMethod = request()->input('method', 'nhan_do');
        $selectedFulfillment = request()->input('fulfillment', 'Tại nhà');
        $selectedAddress = request()->input('address');
        $statusOptions = DeliveryStatus::options();
        $orders = $this->orderOptions($selectedOrderId);

        return view('admin.deliveries.create', compact(
            'customers',
            'employees',
            'orders',
            'selectedOrderId',
            'selectedMethod',
            'selectedFulfillment',
            'selectedAddress',
            'statusOptions',
        ));
    }

    public function assignForOrder(Request $request, DonHang $order)
    {
        $order->load(['booking', 'giaoNhans']);
        $returnLegs = $order->giaoNhans
            ->where('LoaiGiaoNhan', 'GIAO_DO')
            ->sortByDesc('GiaoNhanID');
        $activeLeg = $returnLegs->first(
            fn ($leg): bool => DeliveryStatus::parseForLeg($leg->TrangThai, $leg->LoaiGiaoNhan) !== DeliveryStatus::Cancelled
        );
        $latestLeg = $returnLegs->first();
        $isHomeReturn = $order->requiresHomeDelivery();

        if (! $isHomeReturn || ! in_array($order->statusEnum(), [OrderStatus::Washed, OrderStatus::Delivering], true)) {
            abort(422, 'Đơn hàng chưa sẵn sàng cho phân công giao đồ tại nhà.');
        }

        if ($activeLeg) {
            abort_unless($request->user()?->canPermission('deliveries.edit'), 403);

            return redirect()->route('deliveries.edit', $activeLeg);
        }

        abort_unless($request->user()?->canPermission('deliveries.create'), 403);

        $customers = KhachHang::query()->orderBy('HoTen')->get(['KhachHangID', 'HoTen']);
        $employees = NhanVien::where('TrangThai', 'Hoạt động')
            ->orderBy('HoTen')
            ->get(['NhanVienID', 'HoTen']);
        $orders = $this->orderOptions($order->getKey());
        $selectedAddress = $order->booking?->DiaChiTra ?? $latestLeg?->DiaChi;

        return view('admin.deliveries.create', [
            'customers' => $customers,
            'employees' => $employees,
            'orders' => $orders,
            'selectedOrderId' => $order->getKey(),
            'selectedMethod' => 'giao_do',
            'selectedFulfillment' => 'Tại nhà',
            'selectedAddress' => $selectedAddress,
            'statusOptions' => ['pending' => DeliveryStatus::Pending->label()],
        ]);
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
        $statusOptions = DeliveryStatus::options();
        unset($statusOptions[$delivery->LoaiGiaoNhan === 'GIAO_DO' ? DeliveryStatus::Picking->value : DeliveryStatus::Delivering->value]);

        return view('admin.deliveries.edit', compact('delivery', 'customers', 'employees', 'orders', 'statusOptions'));
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
            ->select(['DonHangID', 'MaDonHang', 'KhachHangID', 'BookingID', 'NgayTao'])
            ->with([
                'khachHang:KhachHangID,HoTen',
                'booking:BookingID,HinhThucNhanDo,DiaChiNhan,HinhThucTraDo,DiaChiTra,NgayHen,GioHen',
            ])
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
