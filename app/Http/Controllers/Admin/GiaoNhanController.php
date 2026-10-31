<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Exceptions\SettledOrderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LuuGiaoNhanRequest;
use App\Models\BookingGiaoNhan;
use App\Models\Booking;
use App\Models\DonHang;
use App\Models\GiaoNhan;
use App\Models\KhachHang;
use App\Models\User;
use App\Services\BookingService;
use App\Services\DeliveryService;
use App\Services\EmployeeAssignment;
use App\Support\FriendlyError;
use App\Support\OrderWorkflow;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GiaoNhanController extends Controller
{
    public function __construct(
        private DeliveryService $deliveryService,
        private BookingService $bookingService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $filters = [
            'employee_id' => $user && ! $user->isManager() ? ($user->NhanVienID ?: -1) : null,
            'search' => $request->input('search'),
            'customer_id' => $request->input('customer_id'),
            'method' => $request->input('method'),
            'status' => $request->input('status'),
            'sort_by' => $request->input('sort_by'),
            'sort_order' => $request->input('sort_order'),
        ];
        $deliveries = $this->deliveryService->getUnifiedDeliveries($filters);

        $inspectableBookingIds = Booking::query()
            ->whereIn('BookingID', $deliveries->getCollection()
                ->where('source_type', 'booking')
                ->where('task_type', 'NHAN_DO')
                ->pluck('booking_id'))
            ->get()
            ->filter(fn (Booking $booking) => $this->bookingService->canInspect($booking))
            ->pluck('BookingID')
            ->all();

        return view('admin.deliveries.index', compact('deliveries', 'inspectableBookingIds'));
    }

    public function create()
    {
        $user = auth()->user();
        $this->requireActiveEmployeeForStaff($user);
        $canAssignEmployee = ! $user || $user->isManager();
        $linkedEmployee = $user?->nhanVien;
        if ($linkedEmployee?->TrangThai !== 'Hoạt động') {
            $linkedEmployee = null;
        }
        $assignedEmployee = $canAssignEmployee ? null : $linkedEmployee;
        $customers = KhachHang::query()->orderBy('HoTen')->get(['KhachHangID', 'HoTen']);
        $employees = $this->assignableEmployees($linkedEmployee?->NhanVienID);
        $employeeNameCounts = $employees->countBy(fn ($employee) => mb_strtolower(trim((string) $employee->HoTen)));
        $defaultEmployeeId = old('employee_id', $linkedEmployee?->NhanVienID);
        $selectedEmployee = $employees->firstWhere('NhanVienID', (int) $defaultEmployeeId);
        $oldEmployeeSearch = old('employee_search');
        if ($oldEmployeeSearch !== null && $selectedEmployee
            && trim($oldEmployeeSearch) !== trim((string) $selectedEmployee->HoTen)) {
            $selectedEmployee = null;
        }
        $selectedOrderId = request()->integer('order_id') ?: null;
        $selectedMethod = request()->input('method', 'nhan_do');
        $selectedFulfillment = request()->input('fulfillment', 'Tại nhà');
        $selectedAddress = request()->input('address');
        $statusOptions = DeliveryStatus::options();
        $orders = $this->orderOptions($selectedOrderId);

        return view('admin.deliveries.create', compact(
            'customers',
            'employees',
            'employeeNameCounts',
            'selectedEmployee',
            'canAssignEmployee',
            'assignedEmployee',
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
        $user = $request->user();
        $this->requireActiveEmployeeForStaff($user);
        $order->load(['booking', 'giaoNhans']);
        $returnLegs = $order->giaoNhans
            ->where('LoaiGiaoNhan', 'GIAO_DO')
            ->sortByDesc('GiaoNhanID');
        $activeLeg = $returnLegs->first(
            fn ($leg): bool => DeliveryStatus::parseForLeg($leg->TrangThai, $leg->LoaiGiaoNhan) !== DeliveryStatus::Cancelled
        );
        $latestLeg = $returnLegs->first();
        $isHomeReturn = $order->requiresHomeDelivery();
        $isBookingReturn = $order->booking !== null;
        $returnMethod = $order->booking?->HinhThucTraDo;
        $bookingCancelled = $order->booking?->statusEnum() === BookingStatus::Cancelled;
        $isEligibleReturnMethod = $isBookingReturn
            ? in_array($returnMethod, ['Tại nhà', 'Tại cửa hàng'], true)
            : $isHomeReturn;

        if (
            $bookingCancelled
            || ! $isEligibleReturnMethod
            || ! in_array($order->statusEnum(), [OrderStatus::Washed, OrderStatus::Delivering], true)
        ) {
            abort(422, 'Đơn hàng chưa sẵn sàng cho phân công phiếu giao trả.');
        }

        if ($activeLeg) {
            if ($isBookingReturn && $activeLeg->HinhThuc !== $returnMethod) {
                abort(422, 'Phiếu giao trả hiện có không khớp với hình thức Booking; cần đối soát trước khi tiếp tục.');
            }

            abort_unless($request->user()?->canPermission('deliveries.edit'), 403);
            $this->restrictToOwnDelivery($activeLeg, $user);

            return redirect()->route(
                'deliveries.edit',
                [$activeLeg] + OrderWorkflow::listReturnParams(),
            );
        }

        abort_unless($request->user()?->canPermission('deliveries.create'), 403);

        $isRetryAfterCancelledReturn = $latestLeg !== null
            && DeliveryStatus::parseForLeg($latestLeg->TrangThai, $latestLeg->LoaiGiaoNhan) === DeliveryStatus::Cancelled;
        if (
            $isBookingReturn
            && $order->statusEnum() !== OrderStatus::Washed
            && ! ($order->statusEnum() === OrderStatus::Delivering && $isRetryAfterCancelledReturn)
        ) {
            abort(422, 'Chỉ được tạo phiếu giao trả sau khi hoàn thành giặt.');
        }

        $customers = KhachHang::query()->orderBy('HoTen')->get(['KhachHangID', 'HoTen']);
        $linkedEmployee = $user?->nhanVien;
        if ($linkedEmployee?->TrangThai !== 'Hoạt động') {
            $linkedEmployee = null;
        }
        $employees = ! $user || $user->isManager()
            ? $this->assignableEmployees($linkedEmployee?->NhanVienID) : new Collection([$linkedEmployee]);
        $employeeNameCounts = $employees->countBy(fn ($employee) => mb_strtolower(trim((string) $employee->HoTen)));
        $orders = $this->orderOptions($order->getKey());
        $selectedFulfillment = $isBookingReturn ? $returnMethod : 'Tại nhà';
        $selectedAddress = $selectedFulfillment === 'Tại nhà'
            ? ($order->booking?->DiaChiTra ?? $latestLeg?->DiaChi)
            : null;
        $selectedEmployee = $employees->firstWhere(
            'NhanVienID',
            (int) old('employee_id', $linkedEmployee?->NhanVienID),
        );
        $oldEmployeeSearch = old('employee_search');
        if ($oldEmployeeSearch !== null && $selectedEmployee
            && trim($oldEmployeeSearch) !== trim((string) $selectedEmployee->HoTen)) {
            $selectedEmployee = null;
        }

        return view('admin.deliveries.create', [
            'customers' => $customers,
            'employees' => $employees,
            'employeeNameCounts' => $employeeNameCounts,
            'selectedEmployee' => $selectedEmployee,
            'canAssignEmployee' => ! $user || $user->isManager(),
            'assignedEmployee' => $user && ! $user->isManager() ? $linkedEmployee : null,
            'orders' => $orders,
            'selectedOrderId' => $order->getKey(),
            'selectedMethod' => 'giao_do',
            'selectedFulfillment' => $selectedFulfillment,
            'selectedAddress' => $selectedAddress,
            'lockDeliveryType' => true,
            'statusOptions' => ['pending' => DeliveryStatus::Pending->label()],
            'backUrl' => route('orders.index'),
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

        $this->restrictToOwnDelivery($delivery, auth()->user());
        $displayCode = 'GN-'.$delivery->GiaoNhanID;

        return view('admin.deliveries.show', compact(
            'delivery',
            'displayCode',
        ));
    }

    public function showBookingDocument(int $bookingDelivery)
    {
        $bookingDocument = $this->deliveryService->findBookingDocument($bookingDelivery);
        if (! $bookingDocument) {
            abort(404);
        }

        $user = auth()->user();
        $this->restrictToOwnBookingDocument($bookingDocument, $user);

        $displayCode = 'BGN-'.$bookingDocument->BookingGiaoNhanID;
        $isIntake = $bookingDocument->LoaiGiaoNhan === 'NHAN_DO';

        return view('admin.deliveries.booking-document', compact(
            'bookingDocument',
            'displayCode',
            'isIntake',
        ));
    }

    public function editBookingDocument(int $bookingDelivery)
    {
        $bookingDocument = $this->deliveryService->findBookingDocument($bookingDelivery);
        abort_if(! $bookingDocument, 404);

        $user = auth()->user();
        $this->restrictToOwnBookingDocument($bookingDocument, $user);
        abort_unless(
            $this->deliveryService->canManageBookingIntake($bookingDocument),
            403,
            'Phiếu nhận đồ không còn ở trạng thái có thể chỉnh sửa.',
        );

        $canAssignEmployee = EmployeeAssignment::canAssignBookingIntake($user);
        $employees = $canAssignEmployee
            ? $this->bookingIntakeAssignableEmployees($user, $bookingDocument->NhanVienID)
            : new Collection([$bookingDocument->nhanVien]);
        $displayCode = 'BGN-'.$bookingDocument->BookingGiaoNhanID;

        return view('admin.deliveries.booking-document-edit', compact(
            'bookingDocument',
            'canAssignEmployee',
            'employees',
            'displayCode',
        ));
    }

    public function updateBookingDocument(Request $request, int $bookingDelivery): RedirectResponse
    {
        $bookingDocument = $this->deliveryService->findBookingDocument($bookingDelivery);
        abort_if(! $bookingDocument, 404);

        $user = $request->user();
        $this->restrictToOwnBookingDocument($bookingDocument, $user);
        abort_unless(
            $this->deliveryService->canManageBookingIntake($bookingDocument),
            403,
            'Phiếu nhận đồ không còn ở trạng thái có thể chỉnh sửa.',
        );

        $canAssignEmployee = EmployeeAssignment::canAssignBookingIntake($user);
        abort_if($request->exists('employee_id') && ! $canAssignEmployee, 403);

        $rules = [
            'pickup_date' => ['nullable', 'date_format:Y-m-d', 'required_with:pickup_time'],
            'pickup_time' => ['nullable', 'date_format:H:i', 'required_with:pickup_date'],
            'address' => [
                $bookingDocument->HinhThuc === 'Tại nhà' ? 'required' : 'nullable',
                'string',
                'max:255',
            ],
        ];
        if ($canAssignEmployee && $user !== null) {
            $rules['employee_id'] = [
                'required',
                'integer',
                EmployeeAssignment::bookingIntakeRule($user, $bookingDocument->NhanVienID),
            ];
        }

        $validated = $request->validate($rules, [
            'employee_id.required' => 'Vui lòng chọn nhân viên phụ trách.',
            'employee_id.integer' => 'Nhân viên phụ trách không hợp lệ.',
            'employee_id.exists' => 'Vui lòng chọn nhân viên đang hoạt động.',
            'employee_id.in' => 'Nhân viên được chọn không thuộc phạm vi phân công hợp lệ.',
            'pickup_date.required_with' => 'Ngày và giờ dự kiến phải được nhập cùng nhau.',
            'pickup_time.required_with' => 'Ngày và giờ dự kiến phải được nhập cùng nhau.',
            'address.required' => 'Vui lòng nhập địa chỉ nhận đồ tại nhà.',
        ]);

        $this->deliveryService->updateBookingIntake($bookingDocument, $validated);

        return redirect()->route('deliveries.booking-documents.show', $bookingDocument)
            ->with('success', 'Phiếu nhận đồ đã được cập nhật.');
    }

    public function advanceProgress(int $id): RedirectResponse
    {
        $delivery = $this->deliveryService->find($id);

        if (! $delivery) {
            abort(404);
        }

        $this->restrictToOwnDelivery($delivery, auth()->user());

        try {
            $delivery = $this->deliveryService->advanceProgress($delivery);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? $exception->getMessage();

            return redirect()->route('deliveries.show', $delivery)->with('error', $message);
        } catch (SettledOrderException $exception) {
            return redirect()->route('deliveries.show', $delivery)->with('error', $exception->getMessage());
        }

        $status = DeliveryStatus::parseForLeg($delivery->TrangThai, $delivery->LoaiGiaoNhan);

        return redirect()->route('deliveries.show', $delivery)
            ->with('success', 'Đã xác nhận tiến độ: '.$status->label().'.');
    }

    public function advanceBookingDocument(int $bookingDelivery): RedirectResponse
    {
        $document = $this->deliveryService->findBookingDocument($bookingDelivery);
        abort_if(! $document, 404);
        $this->restrictToOwnBookingDocument($document, auth()->user());

        try {
            $this->deliveryService->advanceBookingDelivery($document);
        } catch (ValidationException $exception) {
            return redirect()->route('deliveries.booking-documents.show', $document)
                ->with('error', collect($exception->errors())->flatten()->first());
        } catch (SettledOrderException $exception) {
            return redirect()->route('deliveries.booking-documents.show', $document)
                ->with('error', $exception->getMessage());
        }

        return redirect()->route('deliveries.booking-documents.show', $document)
            ->with('success', 'Đã cập nhật tiến độ giao đồ.');
    }

    public function edit(int $id)
    {
        $delivery = $this->deliveryService->find($id);

        if (! $delivery) {
            abort(404);
        }

        $user = auth()->user();
        $this->restrictToOwnDelivery($delivery, $user);
        abort_unless(
            $delivery->LoaiGiaoNhan !== 'GIAO_DO' || $this->deliveryService->canEditReturnDelivery($delivery),
            403,
            'Lịch giao đồ không còn ở trạng thái có thể chỉnh sửa.',
        );
        $canAssignEmployee = ! $user || $user->isManager();
        $assignedEmployee = $canAssignEmployee ? null : $user->nhanVien;

        $customers = KhachHang::query()->orderBy('HoTen')->get(['KhachHangID', 'HoTen']);
        $employees = $canAssignEmployee
            ? $this->assignableEmployees($delivery->NhanVienID) : new Collection([$assignedEmployee]);
        $orders = $this->orderOptions($delivery->DonHangID);
        $statusOptions = DeliveryStatus::options();
        unset($statusOptions[$delivery->LoaiGiaoNhan === 'GIAO_DO' ? DeliveryStatus::Picking->value : DeliveryStatus::Delivering->value]);
        if ($delivery->LoaiGiaoNhan === 'GIAO_DO') {
            unset($statusOptions[DeliveryStatus::Cancelled->value]);
        }

        return view('admin.deliveries.edit', compact('delivery', 'customers', 'employees', 'orders', 'statusOptions', 'canAssignEmployee', 'assignedEmployee'));
    }

    public function update(LuuGiaoNhanRequest $request, int $id)
    {
        $delivery = $this->deliveryService->find($id);

        if (! $delivery) {
            abort(404);
        }

        $this->restrictToOwnDelivery($delivery, $request->user());
        abort_unless(
            $delivery->LoaiGiaoNhan !== 'GIAO_DO' || $this->deliveryService->canEditReturnDelivery($delivery),
            403,
            'Lịch giao đồ không còn ở trạng thái có thể chỉnh sửa.',
        );

        try {
            $this->deliveryService->update($delivery, $request->validated());

            return redirect()->route('deliveries.show', $delivery)->with('success', 'Giao nhận đã được cập nhật.');
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

        $this->restrictToOwnDelivery($delivery, auth()->user());

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

    public function cancelReturnDelivery(int $id): RedirectResponse
    {
        $delivery = $this->deliveryService->find($id);
        abort_if(! $delivery, 404);

        $this->restrictToOwnDelivery($delivery, auth()->user());

        try {
            $this->deliveryService->cancelReturnDelivery($delivery);

            return redirect()->route('deliveries.index')
                ->with('success', 'Đã hủy lịch giao. Đơn hàng và trạng thái thanh toán không thay đổi.');
        } catch (ValidationException $exception) {
            return redirect()->route('deliveries.show', $delivery)->withErrors($exception->errors());
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

    private function requireActiveEmployeeForStaff(?User $user): void
    {
        if ($user && ! $user->isManager()) {
            abort_unless($user->nhanVien?->TrangThai === 'Hoạt động', 403, 'Tài khoản chưa liên kết với nhân viên đang hoạt động.');
        }
    }

    private function restrictToOwnDelivery(GiaoNhan $delivery, ?User $user): void
    {
        if ($user && ! $user->isManager()) {
            $this->requireActiveEmployeeForStaff($user);
            abort_unless($user->NhanVienID && (int) $delivery->NhanVienID === (int) $user->NhanVienID, 403);
        }
    }

    private function restrictToOwnBookingDocument(BookingGiaoNhan $document, ?User $user): void
    {
        if ($user && ! $user->isManager()) {
            $this->requireActiveEmployeeForStaff($user);
            abort_unless($user->NhanVienID && (int) $document->NhanVienID === (int) $user->NhanVienID, 403);
        }
    }

    private function assignableEmployees(?int $currentId = null): Collection
    {
        return EmployeeAssignment::deliveryCandidates($currentId)
            ->orderBy('HoTen')
            ->orderBy('NhanVienID')
            ->get(['NhanVienID', 'HoTen', 'ChucDanh', 'SoDienThoai', 'Email', 'NgayVaoLam']);
    }

    private function bookingIntakeAssignableEmployees(User $user, ?int $currentId = null): Collection
    {
        return EmployeeAssignment::bookingIntakeCandidates($user, $currentId)
            ->orderBy('HoTen')
            ->orderBy('NhanVienID')
            ->get(['NhanVienID', 'HoTen', 'ChucDanh', 'SoDienThoai', 'Email', 'NgayVaoLam']);
    }
}
