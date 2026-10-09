<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Concerns\RejectsSettledRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HoanTatTiepNhanRequest;
use App\Http\Requests\Admin\LuuDonHangRequest;
use App\Models\DanhMucLoaiDoGiat;
use App\Models\DichVu;
use App\Models\DonViTinh;
use App\Models\KhachHang;
use App\Models\KhuyenMai;
use App\Models\LoaiDichVu;
use App\Models\LoaiDoGiat;
use App\Models\NhanVien;
use App\Services\OrderService;
use App\Services\PricingService;
use App\Support\FriendlyError;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class DonHangController extends Controller
{
    use RejectsSettledRecords;

    public function __construct(
        private OrderService $orderService,
        private PricingService $pricingService,
    ) {}

    /**
     * Danh sách nhóm dịch vụ và đơn vị tính lấy từ các bảng tiếng Việt hiện có.
     */
    private function categoryOptions(): array
    {
        return [
            'serviceCategories' => LoaiDichVu::where('TrangThai', 'Hoạt động')
                ->orderBy('TenLoaiDichVu')
                ->get(['LoaiDichVuID', 'TenLoaiDichVu']),
            'units' => DonViTinh::where('TrangThai', 'Hoạt động')->orderBy('TenDonViTinh')->get(['DonViTinhID', 'TenDonViTinh', 'KyHieu']),
        ];
    }

    public function index(Request $request)
    {
        $orders = $this->orderService->getAll([
            'search' => $request->input('search'),
            'customer_id' => $request->input('KhachHangID', $request->input('customer_id')),
            'status' => $request->input('status'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'sort' => $request->input('sort'),
        ]);

        $statusFlow = $this->orderService->getStatusFlow();

        return view('admin.orders.index', compact('orders', 'statusFlow'));
    }

    public function create()
    {
        $customers = KhachHang::with('diemTichLuy')->orderBy('HoTen')->get();
        $services = DichVu::where('TrangThai', 'Hoạt động')->orderBy('TenDichVu')->get();
        $garments = LoaiDoGiat::where('TrangThai', 'Hoạt động')->orderBy('TenLoaiDoGiat')->get();
        $promotions = KhuyenMai::where('TrangThai', 'Hoạt động')->get();
        $employees = NhanVien::where('TrangThai', 'Hoạt động')->orderBy('HoTen')->get(['NhanVienID', 'HoTen']);
        $statusFlow = $this->orderService->getStatusFlow();
        $pricings = $this->pricingService->orderOptions();
        $nextOrderCode = $this->orderService->nextOrderCode();

        return view('admin.orders.create', array_merge(
            compact('customers', 'services', 'garments', 'promotions', 'employees', 'statusFlow', 'pricings', 'nextOrderCode'),
            $this->categoryOptions()
        ));
    }

    public function store(LuuDonHangRequest $request)
    {
        try {
            $order = $this->orderService->create($request->validated());

            $rejection = $this->orderService->promotionRejection();

            if ($rejection) {
                return redirect()->route('orders.show', $order)
                    ->with('error', $rejection.' Đơn hàng vẫn được tạo nhưng không áp dụng voucher.');
            }

            return redirect()->route('orders.show', $order)->with('success', 'Đơn hàng đã được tạo thành công.');
        } catch (ValidationException $exception) {
            return redirect()->route('orders.create')->withErrors($exception->errors())->withInput();
        } catch (\Exception $e) {
            return redirect()->route('orders.create')->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function show(int $id)
    {
        $order = $this->orderService->find($id);

        if (! $order) {
            abort(404);
        }

        $statusFlow = $this->orderService->getStatusFlow();
        $services = collect();
        $garments = collect();
        $units = collect();
        $garmentCategories = collect();
        $inspectionPricings = collect();

        if (
            $order->statusEnum() === OrderStatus::Pending
            && auth()->user()?->can('orders.edit')
        ) {
            $services = DichVu::orderBy('TenDichVu')->get();
            $garments = LoaiDoGiat::orderBy('TenLoaiDoGiat')
                ->get(['LoaiDoGiatID', 'TenLoaiDoGiat', 'DanhMucID']);
            $units = DonViTinh::orderBy('TenDonViTinh')->get();
            $garmentCategories = DanhMucLoaiDoGiat::orderBy('TenDanhMuc')
                ->get(['DanhMucID', 'TenDanhMuc']);
            $inspectionPricings = $this->pricingService->orderOptions();
        }

        return view('admin.orders.show', compact(
            'order',
            'statusFlow',
            'services',
            'garments',
            'units',
            'garmentCategories',
            'inspectionPricings',
        ));
    }

    public function completeReceiving(HoanTatTiepNhanRequest $request, int $id)
    {
        $order = $this->orderService->find($id);

        if (! $order) {
            abort(404);
        }

        try {
            $order = $this->orderService->completeReceivingInspection($order, $request->validated()['items']);
            $rejection = $this->orderService->promotionRejection();

            if ($rejection) {
                return redirect()->route('orders.show', $order)->with(
                    'error',
                    $rejection.' Đơn hàng đã hoàn tất tiếp nhận nhưng không áp dụng voucher.',
                );
            }

            return redirect()->route('orders.show', $order)->with(
                'success',
                'Đã lưu kiểm tra thực tế và hoàn tất tiếp nhận đơn hàng '.$order->MaDonHang.'.',
            );
        } catch (ValidationException $exception) {
            return redirect()->route('orders.show', $order)
                ->withErrors($exception->errors())
                ->withInput();
        } catch (\Throwable $exception) {
            return redirect()->route('orders.show', $order)
                ->with('error', FriendlyError::message($exception))
                ->withInput();
        }
    }

    /**
     * Đơn đã quyết toán chỉ mở cho Chủ cửa hàng (người giữ quyền
     * orders.edit_completed / orders.delete_completed). Nhân viên và Quản lý
     * không bao giờ được cấp 2 quyền này nên vẫn bị khoá như cũ.
     */
    private function canOverrideSettled(): bool
    {
        return auth()->user()?->canPermission('orders.edit_completed') ?? false;
    }

    private function canDeleteSettled(): bool
    {
        return auth()->user()?->canPermission('orders.delete_completed') ?? false;
    }

    /**
     * Kiểm tra đơn đã thanh toán và user không phải chủ cửa hàng.
     * Chủ cửa hàng (owner) vẫn được phép thao tác.
     */
    private function denyIfPaidAndNotOwner(Request $request, $order, string $action): ?Response
    {
        if ($order->isPaid() && ! auth()->user()?->isOwner()) {
            return $this->denySettled(
                $request,
                "Đơn hàng {$order->MaDonHang} đã thanh toán. Bạn không có quyền {$action}.",
                $action === 'xóa' ? route('orders.index') : route('orders.show', $order)
            );
        }

        return null;
    }

    public function edit(Request $request, int $id)
    {
        $order = $this->orderService->find($id);

        if (! $order) {
            abort(404);
        }

        if (
            $order->TrangThai !== OrderStatus::Pending->value
            && ! $this->canOverrideSettled()
        ) {
            return $this->denySettled(
                $request,
                'Chi tiết đơn hàng đã khóa sau khi hoàn tất tiếp nhận.',
                route('orders.show', $order)
            );
        }

        // Kiểm tra đơn đã thanh toán - nhân viên/quản lý không được sửa
        if ($denied = $this->denyIfPaidAndNotOwner($request, $order, 'sửa')) {
            return $denied;
        }

        if ($order->isLocked() && ! $this->canOverrideSettled()) {
            return $this->denySettled(
                $request,
                'Đơn hàng '.$order->MaDonHang.' đã quyết toán nên chỉ có thể xem, không thể sửa.',
                route('orders.show', $order)
            );
        }

        $customers = KhachHang::with('diemTichLuy')->orderBy('HoTen')->get();
        $services = DichVu::where('TrangThai', 'Hoạt động')->orderBy('TenDichVu')->get();
        $garments = LoaiDoGiat::where('TrangThai', 'Hoạt động')->orderBy('TenLoaiDoGiat')->get();
        $promotions = KhuyenMai::where('TrangThai', 'Hoạt động')->get();
        $employees = NhanVien::where(fn ($query) => $query->where('TrangThai', 'Hoạt động')->orWhere('NhanVienID', $order->NhanVienID))->orderBy('HoTen')->get(['NhanVienID', 'HoTen']);
        $statusFlow = $this->orderService->getStatusFlow();
        $pricings = $this->pricingService->orderOptions();

        return view('admin.orders.edit', array_merge(
            compact('order', 'customers', 'services', 'garments', 'promotions', 'employees', 'statusFlow', 'pricings'),
            $this->categoryOptions()
        ));
    }

    public function update(LuuDonHangRequest $request, int $id)
    {
        $order = $this->orderService->find($id);

        if (! $order) {
            abort(404);
        }

        if (
            $order->TrangThai !== OrderStatus::Pending->value
            && ! $this->canOverrideSettled()
        ) {
            return $this->denySettled(
                $request,
                'Chi tiết đơn hàng đã khóa sau khi hoàn tất tiếp nhận.',
                route('orders.show', $order)
            );
        }

        // Kiểm tra đơn đã thanh toán - nhân viên/quản lý không được sửa
        if ($denied = $this->denyIfPaidAndNotOwner($request, $order, 'chỉnh sửa')) {
            return $denied;
        }

        $override = $this->canOverrideSettled();

        if ($order->isLocked() && ! $override) {
            return $this->denySettled(
                $request,
                'Đơn hàng '.$order->MaDonHang.' đã quyết toán nên không thể chỉnh sửa.',
                route('orders.show', $order)
            );
        }

        try {
            $this->orderService->update($order, $request->validated(), $override);

            $rejection = $this->orderService->promotionRejection();

            if ($rejection) {
                return redirect()->route('orders.show', $order)
                    ->with('error', $rejection.' Đơn hàng vẫn được lưu nhưng không áp dụng voucher.');
            }

            return redirect()->route('orders.show', $order)->with('success', 'Đơn hàng đã được cập nhật.');
        } catch (ValidationException $exception) {
            return redirect()->route('orders.edit', $order)
                ->withErrors($exception->errors())
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->route('orders.edit', $order)->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function destroy(Request $request, int $id)
    {
        $order = $this->orderService->find($id);

        if (! $order) {
            abort(404);
        }

        // Kiểm tra đơn đã thanh toán - nhân viên/quản lý không được xóa
        if ($denied = $this->denyIfPaidAndNotOwner($request, $order, 'xóa')) {
            return $denied;
        }

        $override = $this->canDeleteSettled();

        if ($order->isLocked() && ! $override) {
            return $this->denySettled(
                $request,
                'Đơn hàng '.$order->MaDonHang.' đã quyết toán nên không thể xóa.',
                route('orders.index')
            );
        }

        try {
            $this->orderService->delete($order, $override);

            return redirect()->route('orders.index')->with('success', 'Đơn hàng đã được xóa.');
        } catch (\Exception $e) {
            return redirect()->route('orders.index')->with('error', FriendlyError::message($e));
        }
    }

    public function updateStatus(Request $request, int $id)
    {
        $order = $this->orderService->find($id);

        if (! $order) {
            abort(404);
        }

        // Kiểm tra đơn đã thanh toán - nhân viên/quản lý không được đổi trạng thái
        if ($denied = $this->denyIfPaidAndNotOwner($request, $order, 'đổi trạng thái')) {
            return $denied;
        }

        $override = $this->canOverrideSettled();

        if ($order->isLocked() && ! $override) {
            return $this->denySettled(
                $request,
                'Đơn hàng '.$order->MaDonHang.' đã quyết toán nên không thể đổi trạng thái.',
                route('orders.show', $order)
            );
        }

        try {
            $validated = $request->validate(['cancellation_reason' => ['nullable', 'string', 'max:500']]);
            $this->orderService->updateStatus($order, (string) $request->input('TrangThai', $request->input('status')), $override, $validated['cancellation_reason'] ?? null);

            return back()->with('success', 'Trạng thái đơn hàng đã được cập nhật.');
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        } catch (\Exception $e) {
            return back()->with('error', FriendlyError::message($e));
        }
    }
}
