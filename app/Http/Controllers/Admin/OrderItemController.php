<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RejectsSettledRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderItemRequest;
use App\Models\BangGia;
use App\Models\ChiTietDonHang;
use App\Models\DichVu;
use App\Models\DonHang;
use App\Models\DonViTinh;
use App\Models\LoaiDoGiat;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderItemController extends Controller
{
    use RejectsSettledRecords;

    /**
     * Đơn đã quyết toán thì các dòng mặt hàng của đơn cũng bị khoá theo.
     */
    private function assertOrderEditable(?ChiTietDonHang $item, Request $request, string $fallbackUrl): ?Response
    {
        $order = $item?->donHang;

        if ($order?->isLocked()) {
            return $this->rejectSettled(
                $request,
                'Đơn hàng '.$order->MaDonHang.' đã quyết toán nên không thể thay đổi chi tiết mặt hàng.',
                $fallbackUrl
            );
        }

        return null;
    }

    public function index(Request $request)
    {
        $items = ChiTietDonHang::query();

        if (!empty($request->input('DonHangID', $request->input('order_id')))) {
            $items->where('DonHangID', $request->input('DonHangID', $request->input('order_id')));
        }

        // Nạp sẵn hóa đơn + thanh toán của đơn để isLocked() không N+1
        $items = $items->with('donHang', 'donHang.hoaDons', 'donHang.thanhToans', 'dichVu')
            ->latest('ChiTietDonHangID')
            ->paginate(10);

        $orders = DonHang::orderBy('MaDonHang')->get();

        return view('admin.order-items.index', compact('items', 'orders'));
    }

    public function create()
    {
        $orders = DonHang::orderBy('MaDonHang')->get();
        $services = DichVu::where('TrangThai', 'Hoạt động')->orderBy('TenDichVu')->get();
        $garments = LoaiDoGiat::where('TrangThai', 'Hoạt động')->orderBy('TenLoaiDoGiat')->get();
        $units = DonViTinh::where('TrangThai', 'Hoạt động')->orderBy('TenDonViTinh')->get();

        return view('admin.order-items.create', compact('orders', 'services', 'garments', 'units'));
    }

    public function store(OrderItemRequest $request)
    {
        $order = DonHang::find($request->input('DonHangID', $request->input('order_id')));

        if ($order?->isLocked()) {
            return $this->rejectSettled(
                $request,
                'Đơn hàng '.$order->MaDonHang.' đã quyết toán nên không thể thêm chi tiết mặt hàng.',
                route('order-items.index')
            );
        }

        try {
            $data = $request->validated();

            $data = $this->withResolvedPricing($data);
            $data = $this->withComputedSubtotal($data);

            ChiTietDonHang::create($data);

            return redirect()->route('order-items.index')->with('success', 'Chi tiết đơn hàng đã được thêm.');
        } catch (\Exception $e) {
            return redirect()->route('order-items.create')->with('error', \App\Support\FriendlyError::message($e))->withInput();
        }
    }

    public function show(int $id)
    {
        $item = ChiTietDonHang::with('donHang', 'dichVu')->findOrFail($id);

        return view('admin.order-items.show', compact('item'));
    }

    public function edit(int $id)
    {
        $item = ChiTietDonHang::with('donHang')->findOrFail($id);

        if ($rejected = $this->assertOrderEditable($item, request(), route('order-items.index'))) {
            return $rejected;
        }

        $orders = DonHang::orderBy('MaDonHang')->get();
        $services = DichVu::where('TrangThai', 'Hoạt động')->orderBy('TenDichVu')->get();
        $garments = LoaiDoGiat::where('TrangThai', 'Hoạt động')->orderBy('TenLoaiDoGiat')->get();
        $units = DonViTinh::where('TrangThai', 'Hoạt động')->orderBy('TenDonViTinh')->get();

        return view('admin.order-items.edit', compact('item', 'orders', 'services', 'garments', 'units'));
    }

    public function update(OrderItemRequest $request, int $id)
    {
        $item = ChiTietDonHang::with('donHang')->findOrFail($id);

        if ($rejected = $this->assertOrderEditable($item, $request, route('order-items.index'))) {
            return $rejected;
        }

        try {
            $data = $request->validated();

            $data = $this->withResolvedPricing($data);
            $data = $this->withComputedSubtotal($data);

            $item->update($data);

            return redirect()->route('order-items.index')->with('success', 'Chi tiết đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('order-items.edit', $id)->with('error', \App\Support\FriendlyError::message($e))->withInput();
        }
    }

    public function destroy(Request $request, int $id)
    {
        $item = ChiTietDonHang::with('donHang')->findOrFail($id);

        if ($rejected = $this->assertOrderEditable($item, $request, route('order-items.index'))) {
            return $rejected;
        }

        try {
            $item->delete();

            return redirect()->route('order-items.index')->with('success', 'Đã xóa chi tiết.');
        } catch (\Exception $e) {
            return redirect()->route('order-items.index')->with('error', \App\Support\FriendlyError::message($e));
        }
    }

    /**
     * Tự động điền Đơn giá và Đơn vị tính từ bảng BangGia nếu form không gửi.
     */
    private function withResolvedPricing(array $data): array
    {
        if (empty($data['DonGia'])) {
            $pricing = BangGia::getLatestPricing(
                (int) ($data['DichVuID'] ?? 0),
                (int) ($data['LoaiDoGiatID'] ?? 0),
                ! empty($data['DonViTinhID']) ? (int) $data['DonViTinhID'] : null
            );

            if ($pricing) {
                $data['DonGia'] = $pricing->DonGia;
                $data['DonViTinhID'] = $data['DonViTinhID'] ?? $pricing->DonViTinhID;
            }
        }

        return $data;
    }

    /**
     * Tính Thành tiền: đơn vị kg → Đơn giá × Số lượng × Khối lượng;
     * đơn vị khác → Đơn giá × Số lượng (bỏ qua khối lượng).
     */
    private function withComputedSubtotal(array $data): array
    {
        $unit = ! empty($data['DonViTinhID'])
            ? DonViTinh::find($data['DonViTinhID'])?->KyHieu
            : null;

        $isWeightUnit = BangGia::isWeightUnit($unit);
        $weight = max(0, (float) ($data['KhoiLuong'] ?? 0));
        $price = (float) ($data['DonGia'] ?? 0);
        $quantity = (float) ($data['SoLuong'] ?? 0);

        $data['ThanhTien'] = $isWeightUnit && $weight > 0
            ? round($price * $quantity * $weight, 2)
            : round($price * $quantity, 2);

        return $data;
    }
}
