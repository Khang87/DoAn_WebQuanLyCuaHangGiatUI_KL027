<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RejectsSettledRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderRequest;
use App\Models\Customer;
use App\Models\Garment;
use App\Models\Pricing;
use App\Models\Promotion;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\GarmentService;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use RejectsSettledRecords;

    public function __construct(
        private OrderService $orderService,
        private GarmentService $garmentService,
    ) {}

    /**
     * Bảng giá cho UI: mỗi cặp service_id + garment_id chỉ giữ bản giá lịch sử
     * mới nhất, đúng thứ tự mà OrderService/Pricing::getLatestPricing() chọn.
     */
    private function pricingOptions()
    {
        return Pricing::where('status', 'active')
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get()
            ->unique(fn ($pricing) => $pricing->service_id.'-'.$pricing->garment_id)
            ->values();
    }

    /**
     * Danh sách nhóm dịch vụ và nhóm loại đồ đều lấy từ database, không hardcode.
     */
    private function categoryOptions(): array
    {
        return [
            'serviceCategories' => ServiceCategory::where('status', 'active')->orderBy('name')->get(),
            'garmentCategories' => $this->garmentService->getCategories(),
            'units' => Pricing::unitOptions(),
        ];
    }

    public function index(Request $request)
    {
        $orders = $this->orderService->getAll([
            'search' => $request->input('search'),
            'customer_id' => $request->input('customer_id'),
            'status' => $request->input('status'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'sort' => $request->input('sort'),
        ]);

        $customers = Customer::orderBy('name')->get();
        $statusFlow = $this->orderService->getStatusFlow();

        return view('admin.orders.index', compact('orders', 'customers', 'statusFlow'));
    }

    public function create()
    {
        $customers = Customer::orderBy('name')->get();
        $services = Service::where('status', 'active')->orderBy('name')->get();
        $garments = Garment::where('status', 'active')->orderBy('name')->get();
        $promotions = Promotion::where('status', 'active')->get();
        $employees = User::where('role', '!=', 'customer')->orderBy('name')->get();
        $statusFlow = $this->orderService->getStatusFlow();
        $pricings = $this->pricingOptions();

        return view('admin.orders.create', array_merge(
            compact('customers', 'services', 'garments', 'promotions', 'employees', 'statusFlow', 'pricings'),
            $this->categoryOptions()
        ));
    }

    public function store(OrderRequest $request)
    {
        try {
            $order = $this->orderService->create($request->validated());

            $rejection = $this->orderService->promotionRejection();

            if ($rejection) {
                return redirect()->route('orders.show', $order)
                    ->with('error', $rejection.' Đơn hàng vẫn được tạo nhưng không áp dụng voucher.');
            }

            return redirect()->route('orders.show', $order)->with('success', 'Đơn hàng đã được tạo thành công.');
        } catch (\Exception $e) {
            return redirect()->route('orders.create')->with('error', \App\Support\FriendlyError::message($e))->withInput();
        }
    }

    public function show(int $id)
    {
        $order = $this->orderService->find($id);

        if (!$order) {
            abort(404);
        }

        $statusFlow = $this->orderService->getStatusFlow();

        return view('admin.orders.show', compact('order', 'statusFlow'));
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
    private function denyIfPaidAndNotOwner(Request $request, $order, string $action): ?\Illuminate\Http\Response
    {
        if ($order->isPaid() && ! auth()->user()?->isOwner()) {
            return $this->denySettled(
                $request,
                "Đơn hàng {$order->code} đã thanh toán. Bạn không có quyền {$action}.",
                $action === 'xóa' ? route('orders.index') : route('orders.show', $order)
            );
        }
        return null;
    }

    public function edit(Request $request, int $id)
    {
        $order = $this->orderService->find($id);

        if (!$order) {
            abort(404);
        }

        // Kiểm tra đơn đã thanh toán - nhân viên/quản lý không được sửa
        if ($denied = $this->denyIfPaidAndNotOwner($request, $order, 'sửa')) {
            return $denied;
        }

        if ($order->isLocked() && ! $this->canOverrideSettled()) {
            return $this->denySettled(
                $request,
                'Đơn hàng '.$order->code.' đã quyết toán nên chỉ có thể xem, không thể sửa.',
                route('orders.show', $order)
            );
        }

        $customers = Customer::orderBy('name')->get();
        $services = Service::where('status', 'active')->orderBy('name')->get();
        $garments = Garment::where('status', 'active')->orderBy('name')->get();
        $promotions = Promotion::where('status', 'active')->get();
        $employees = User::where('role', '!=', 'customer')->orderBy('name')->get();
        $statusFlow = $this->orderService->getStatusFlow();
        $pricings = $this->pricingOptions();

        return view('admin.orders.edit', array_merge(
            compact('order', 'customers', 'services', 'garments', 'promotions', 'employees', 'statusFlow', 'pricings'),
            $this->categoryOptions()
        ));
    }

    public function update(OrderRequest $request, int $id)
    {
        $order = $this->orderService->find($id);

        if (!$order) {
            abort(404);
        }

        // Kiểm tra đơn đã thanh toán - nhân viên/quản lý không được sửa
        if ($denied = $this->denyIfPaidAndNotOwner($request, $order, 'chỉnh sửa')) {
            return $denied;
        }

        $override = $this->canOverrideSettled();

        if ($order->isLocked() && ! $override) {
            return $this->denySettled(
                $request,
                'Đơn hàng '.$order->code.' đã quyết toán nên không thể chỉnh sửa.',
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

            return redirect()->route('orders.index')->with('success', 'Đơn hàng đã được cập nhật.');
        } catch (\Exception $e) {
            return redirect()->route('orders.edit', $order)->with('error', \App\Support\FriendlyError::message($e))->withInput();
        }
    }

    public function destroy(Request $request, int $id)
    {
        $order = $this->orderService->find($id);

        if (!$order) {
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
                'Đơn hàng '.$order->code.' đã quyết toán nên không thể xóa.',
                route('orders.index')
            );
        }

        try {
            $this->orderService->delete($order, $override);

            return redirect()->route('orders.index')->with('success', 'Đơn hàng đã được xóa.');
        } catch (\Exception $e) {
            return redirect()->route('orders.index')->with('error', \App\Support\FriendlyError::message($e));
        }
    }

    public function updateStatus(Request $request, int $id)
    {
        $order = $this->orderService->find($id);

        if (!$order) {
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
                'Đơn hàng '.$order->code.' đã quyết toán nên không thể đổi trạng thái.',
                route('orders.show', $order)
            );
        }

        try {
            $this->orderService->updateStatus($order, (string) $request->input('status'), $override);

            return back()->with('success', 'Trạng thái đơn hàng đã được cập nhật.');
        } catch (\Exception $e) {
            return back()->with('error', \App\Support\FriendlyError::message($e));
        }
    }
}
