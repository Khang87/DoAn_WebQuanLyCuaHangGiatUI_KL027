<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Exceptions\SettledOrderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LuuGiaoNhanRequest;
use App\Models\Booking;
use App\Models\BookingGiaoNhan;
use App\Models\DonHang;
use App\Models\GiaoNhan;
use App\Models\KhachHang;
use App\Models\User;
use App\Services\BookingService;
use App\Services\DeliveryService;
use App\Services\EmployeeAssignment;
use App\Support\BookingTimeWindow;
use App\Support\FriendlyError;
use App\Support\OrderWorkflow;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GiaoNhanController extends Controller
{
    private const DELIVERY_LIST_QUERY_PARAMETERS = [
        'page',
        'search',
        'customer_id',
        'method',
        'status',
        'sort_by',
        'sort_order',
    ];

    public function __construct(
        private DeliveryService $deliveryService,
        private BookingService $bookingService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $filters = [
            'employee_id' => $user && ! EmployeeAssignment::canManageAssignments($user) ? ($user->NhanVienID ?: -1) : null,
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
        $this->requireActiveEmployeeForNewDelivery($user);
        $canAssignEmployee = EmployeeAssignment::canAssignToOtherEmployees($user);
        $linkedEmployee = $user?->nhanVien;
        if ($linkedEmployee?->TrangThai !== 'Hoạt động') {
            $linkedEmployee = null;
        }
        $assignedEmployee = $canAssignEmployee ? null : $linkedEmployee;
        $customers = KhachHang::query()->orderBy('HoTen')->get(['KhachHangID', 'HoTen']);
        $employees = $this->assignableEmployees();
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
        $statusOptions = DeliveryStatus::optionsForLeg($selectedMethod === 'giao_do' ? 'GIAO_DO' : 'NHAN_DO');
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
        $this->requireActiveEmployeeForNewDelivery($user);
        $order->load(['booking', 'giaoNhans']);
        $returnLegs = $order->giaoNhans
            ->where('LoaiGiaoNhan', 'GIAO_DO')
            ->sortByDesc('GiaoNhanID');
        $activeLeg = $returnLegs->first(
            fn ($leg): bool => DeliveryStatus::parseForLeg($leg->TrangThai, $leg->LoaiGiaoNhan) !== DeliveryStatus::Cancelled
        );
        $latestLeg = $returnLegs->first();
        $isBookingReturn = $order->booking !== null;
        $returnMethod = $order->booking?->HinhThucTraDo
            ?? $latestLeg?->HinhThuc
            ?? ($order->requiresHomeDelivery() ? 'Tại nhà' : null);
        $bookingCancelled = $order->booking?->statusEnum() === BookingStatus::Cancelled;
        $isEligibleReturnMethod = in_array($returnMethod, ['Tại nhà', 'Tại cửa hàng'], true);
        $eligibleActiveOrderStatuses = $isBookingReturn
            ? [OrderStatus::Washed, OrderStatus::Delivering]
            : [OrderStatus::Received, OrderStatus::Washing, OrderStatus::Washed, OrderStatus::Delivering];

        if (
            $bookingCancelled
            || ! $isEligibleReturnMethod
            || ! in_array($order->statusEnum(), $eligibleActiveOrderStatuses, true)
        ) {
            abort(422, 'Đơn hàng chưa sẵn sàng cho phân công phiếu giao trả.');
        }

        if ($activeLeg) {
            if ($isBookingReturn && $activeLeg->HinhThuc !== $returnMethod) {
                abort(422, 'Phiếu giao hiện có không khớp với hình thức đặt lịch; cần đối soát trước khi tiếp tục.');
            }

            abort_unless($request->user()?->canPermission('deliveries.edit'), 403);
            $this->restrictToOwnDelivery($activeLeg, $user);

            return redirect()->route(
                'deliveries.edit',
                [$activeLeg] + OrderWorkflow::listReturnParams(),
            );
        }

        abort_unless($request->user()?->canPermission('deliveries.create'), 403);

        if (! in_array($order->statusEnum(), [OrderStatus::Washed, OrderStatus::Delivering], true)) {
            abort(422, 'Chỉ đơn đã hoàn thành giặt mới được tạo lại phiếu giao trả.');
        }

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
        $canAssignEmployee = EmployeeAssignment::canAssignToOtherEmployees($user);
        $employees = $canAssignEmployee
            ? $this->assignableEmployees() : new Collection(array_filter([$linkedEmployee]));
        $employeeNameCounts = $employees->countBy(fn ($employee) => mb_strtolower(trim((string) $employee->HoTen)));
        $orders = $this->orderOptions($order->getKey());
        $selectedFulfillment = $returnMethod;
        $selectedAddress = $selectedFulfillment === 'Tại nhà'
            ? ($order->booking?->DiaChiTra ?? $latestLeg?->DiaChi)
            : config('services.google_routes.store_address');
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
            'canAssignEmployee' => $canAssignEmployee,
            'assignedEmployee' => $user && ! $canAssignEmployee ? $linkedEmployee : null,
            'orders' => $orders,
            'selectedOrderId' => $order->getKey(),
            'selectedMethod' => 'giao_do',
            'selectedFulfillment' => $selectedFulfillment,
            'selectedAddress' => $selectedAddress,
            'lockDeliveryType' => true,
            'statusOptions' => DeliveryStatus::optionsForLeg('GIAO_DO'),
            'backUrl' => route('orders.index'),
        ]);
    }

    public function store(LuuGiaoNhanRequest $request)
    {
        try {
            $delivery = $this->deliveryService->create($request->validated());

            if ($redirect = $this->deliveryListRedirect($request)) {
                return $redirect->with('success', 'Giao nhận đã được tạo thành công.');
            }

            return redirect()->route('deliveries.show', $delivery)->with('success', 'Giao nhận đã được tạo thành công.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (SettledOrderException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        } catch (\Exception $e) {
            return redirect()->route('deliveries.create', $this->deliveryListParameters($request))
                ->with('error', FriendlyError::message($e))
                ->withInput();
        }
    }

    public function storeForOrder(LuuGiaoNhanRequest $request, DonHang $order)
    {
        $data = $request->validated();
        abort_unless(
            (int) $data['order_id'] === (int) $order->getKey() && $data['method'] === 'giao_do',
            422,
            'Phiếu giao phải khớp với đơn hàng được chọn.',
        );

        return $this->store($request);
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
        $assignedEmployee = $user?->isEmployee()
            ? $bookingDocument->booking?->nhanVienXacNhan
            : $bookingDocument->nhanVien;
        $employees = $canAssignEmployee
            ? $this->bookingIntakeAssignableEmployees()
            : new Collection($assignedEmployee === null ? [] : [$assignedEmployee]);
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

        // Validation custom với so sánh giờ hẹn
        $rules = [
            'pickup_date' => ['required', 'date_format:Y-m-d'],
            'pickup_time' => ['required', 'date_format:H:i'],
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
                EmployeeAssignment::bookingIntakeRule(),
            ];
        }

        $validated = $request->validate($rules, [
            'employee_id.required' => 'Vui lòng chọn nhân viên phụ trách.',
            'employee_id.integer' => 'Nhân viên phụ trách không hợp lệ.',
            'employee_id.exists' => 'Vui lòng chọn nhân viên đang hoạt động.',
            'employee_id.in' => 'Nhân viên được chọn không thuộc phạm vi phân công hợp lệ.',
            'pickup_date.required' => 'Vui lòng chọn ngày nhận đồ dự kiến.',
            'pickup_time.required' => 'Vui lòng chọn giờ nhận đồ dự kiến.',
            'pickup_date.date_format' => 'Ngày nhận đồ không hợp lệ.',
            'pickup_time.date_format' => 'Giờ nhận đồ không hợp lệ.',
            'address.required' => 'Vui lòng nhập địa chỉ nhận đồ tại nhà.',
        ]);

        $scheduledAt = Carbon::createFromFormat(
            '!Y-m-d H:i',
            $validated['pickup_date'].' '.$validated['pickup_time'],
            config('app.timezone'),
        );
        $scheduleUnchanged = $bookingDocument->ThoiGianDuKien?->format('Y-m-d H:i') === $scheduledAt->format('Y-m-d H:i');

        if (! $scheduleUnchanged && ! BookingTimeWindow::allows($validated['pickup_time'])) {
            throw ValidationException::withMessages([
                'pickup_time' => BookingTimeWindow::DELIVERY_MESSAGE,
            ]);
        }

        if ($scheduledAt->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages([
                'pickup_time' => 'Thời gian giao nhận phải sau thời điểm hiện tại.',
            ]);
        }

        // Custom validation: so sánh với giờ hẹn của booking
        $appointmentDateTime = $bookingDocument->booking?->getAppointmentDateTime();
        if ($appointmentDateTime) {
            if ($scheduledAt->isBefore($appointmentDateTime)) {
                throw ValidationException::withMessages([
                    'pickup_date' => 'Thời gian nhận đồ phải bằng hoặc sau thời gian khách đã đặt lịch hẹn ('.
                        $appointmentDateTime->format('d/m/Y H:i').').',
                ]);
            }
        }

        $validated['pickup_date'] = $validated['pickup_date'];
        $validated['pickup_time'] = $validated['pickup_time'];

        $this->deliveryService->updateBookingIntake($bookingDocument, $validated);

        if ($redirect = $this->deliveryListRedirect($request)) {
            return $redirect->with('success', 'Phiếu nhận đồ đã được cập nhật.');
        }

        return redirect()->route('deliveries.booking-documents.show', $bookingDocument)
            ->with('success', 'Phiếu nhận đồ đã được cập nhật.');
    }

    public function advanceProgress(Request $request, int $id): RedirectResponse
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

            if ($redirect = $this->deliveryListRedirect($request)) {
                return $redirect->with('error', $message);
            }

            return redirect()->route('deliveries.show', $delivery)->with('error', $message);
        } catch (SettledOrderException $exception) {
            if ($redirect = $this->deliveryListRedirect($request)) {
                return $redirect->with('error', $exception->getMessage());
            }

            return redirect()->route('deliveries.show', $delivery)->with('error', $exception->getMessage());
        }

        $status = DeliveryStatus::parseForLeg($delivery->TrangThai, $delivery->LoaiGiaoNhan);

        if ($redirect = $this->deliveryListRedirect($request)) {
            return $redirect->with(
                'success',
                'Đã xác nhận tiến độ: '.DeliveryStatus::labelForLeg($status, $delivery->LoaiGiaoNhan).'.',
            );
        }

        return redirect()->route('deliveries.show', $delivery)
            ->with('success', 'Đã xác nhận tiến độ: '.DeliveryStatus::labelForLeg($status, $delivery->LoaiGiaoNhan).'.');
    }

    public function advanceBookingDocument(Request $request, int $bookingDelivery): RedirectResponse
    {
        $document = $this->deliveryService->findBookingDocument($bookingDelivery);
        abort_if(! $document, 404);
        $this->restrictToOwnBookingDocument($document, auth()->user());

        try {
            if ($document->LoaiGiaoNhan === 'NHAN_DO') {
                $this->deliveryService->startBookingIntake($document);

                return redirect()->route('bookings.inspection', $document->BookingID)
                    ->with('success', 'Đã bắt đầu tiếp nhận đồ. Hãy kiểm tra thực tế trước khi tạo đơn hàng.');
            }

            $this->deliveryService->advanceBookingDelivery($document);
        } catch (ValidationException $exception) {
            if ($redirect = $this->deliveryListRedirect($request)) {
                return $redirect->with('error', collect($exception->errors())->flatten()->first());
            }

            return redirect()->route('deliveries.booking-documents.show', $document)
                ->with('error', collect($exception->errors())->flatten()->first());
        } catch (SettledOrderException $exception) {
            if ($redirect = $this->deliveryListRedirect($request)) {
                return $redirect->with('error', $exception->getMessage());
            }

            return redirect()->route('deliveries.booking-documents.show', $document)
                ->with('error', $exception->getMessage());
        }

        if ($redirect = $this->deliveryListRedirect($request)) {
            return $redirect->with('success', 'Đã cập nhật tiến độ giao đồ.');
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
        $canAssignEmployee = EmployeeAssignment::canAssignToOtherEmployees($user);
        $assignedEmployee = $canAssignEmployee ? null : $user->nhanVien;

        $customers = KhachHang::query()->orderBy('HoTen')->get(['KhachHangID', 'HoTen']);
        $employees = $canAssignEmployee
            ? $this->assignableEmployees() : new Collection([$assignedEmployee]);
        $orders = $this->orderOptions($delivery->DonHangID);
        $statusOptions = DeliveryStatus::optionsForLeg($delivery->LoaiGiaoNhan);
        unset($statusOptions[$delivery->LoaiGiaoNhan === 'GIAO_DO' ? DeliveryStatus::Picking->value : DeliveryStatus::Delivering->value]);
        if (DeliveryStatus::parseForLeg($delivery->TrangThai, $delivery->LoaiGiaoNhan) === DeliveryStatus::Completed) {
            unset($statusOptions[DeliveryStatus::Cancelled->value]);
        }
        $canCancelReturnDelivery = $delivery->LoaiGiaoNhan === 'GIAO_DO'
            && auth()->user()?->canPermission('deliveries.delete')
            && $this->deliveryService->canCancelReturnDelivery($delivery);
        if ($delivery->LoaiGiaoNhan === 'GIAO_DO' && ! $canCancelReturnDelivery) {
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
        if ($request->input('status') === DeliveryStatus::Cancelled->value) {
            abort_if(
                DeliveryStatus::parseForLeg($delivery->TrangThai, $delivery->LoaiGiaoNhan) === DeliveryStatus::Completed,
                422,
                'Không thể hủy phiếu giao nhận đã hoàn thành.',
            );
            abort_unless($request->user()?->canPermission('deliveries.delete'), 403);
        }

        try {
            $data = $request->validated();
            if (($data['status'] ?? null) === DeliveryStatus::Cancelled->value) {
                if ($delivery->LoaiGiaoNhan === 'GIAO_DO') {
                    $this->deliveryService->cancelReturnDelivery($delivery);
                } else {
                    $this->deliveryService->update($delivery, $data);
                }

                return ($this->deliveryListRedirect($request) ?? redirect()->route('deliveries.index'))
                    ->with(
                        'success',
                        $delivery->LoaiGiaoNhan === 'GIAO_DO'
                            ? 'Đã hủy lịch giao. Đơn hàng và trạng thái thanh toán không thay đổi.'
                            : 'Đã hủy lịch nhận đồ và phiếu giao đi kèm (nếu có). Đơn hàng và trạng thái thanh toán không thay đổi.',
                    );
            }

            $this->deliveryService->update($delivery, $data);

            if ($redirect = $this->deliveryListRedirect($request)) {
                return $redirect->with('success', 'Giao nhận đã được cập nhật.');
            }

            return redirect()->route('deliveries.show', $delivery)->with('success', 'Giao nhận đã được cập nhật.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (SettledOrderException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        } catch (\Exception $e) {
            return redirect()->route(
                'deliveries.edit',
                [$delivery] + $this->deliveryListParameters($request),
            )->with('error', FriendlyError::message($e))->withInput();
        }
    }

    public function destroy(Request $request, int $id)
    {
        $delivery = $this->deliveryService->find($id);

        if (! $delivery) {
            abort(404);
        }

        $this->restrictToOwnDelivery($delivery, auth()->user());

        try {
            if (! $this->deliveryService->delete($delivery)) {
                return ($this->deliveryListRedirect($request) ?? redirect()->route('deliveries.index'))
                    ->with('error', 'Không thể xóa giao nhận. Vui lòng thử lại.');
            }

            return ($this->deliveryListRedirect($request) ?? redirect()->route('deliveries.index'))
                ->with('success', 'Đã xóa giao nhận.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (SettledOrderException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        } catch (\Exception $e) {
            return ($this->deliveryListRedirect($request) ?? redirect()->route('deliveries.index'))
                ->with('error', FriendlyError::message($e));
        }
    }

    public function cancelReturnDelivery(Request $request, int $id): RedirectResponse
    {
        $delivery = $this->deliveryService->find($id);
        abort_if(! $delivery, 404);

        $this->restrictToOwnDelivery($delivery, auth()->user());

        try {
            $this->deliveryService->cancelReturnDelivery($delivery);

            return ($this->deliveryListRedirect($request) ?? redirect()->route('deliveries.index'))
                ->with('success', 'Đã hủy lịch giao. Đơn hàng và trạng thái thanh toán không thay đổi.');
        } catch (ValidationException $exception) {
            if ($redirect = $this->deliveryListRedirect($request)) {
                return $redirect->withErrors($exception->errors());
            }

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

    private function deliveryListRedirect(Request $request): ?RedirectResponse
    {
        if ($request->query('return_to') !== 'deliveries') {
            return null;
        }

        $query = array_intersect_key(
            $request->query(),
            array_flip(self::DELIVERY_LIST_QUERY_PARAMETERS),
        );

        return redirect()->route('deliveries.index', $query);
    }

    /**
     * @return array<string, mixed>
     */
    private function deliveryListParameters(Request $request): array
    {
        $query = array_intersect_key(
            $request->query(),
            array_flip(self::DELIVERY_LIST_QUERY_PARAMETERS),
        );

        if ($request->query('return_to') === 'deliveries') {
            $query['return_to'] = 'deliveries';
        }

        return $query;
    }

    private function requireActiveEmployeeForNewDelivery(?User $user): void
    {
        if ($user && ! EmployeeAssignment::canAssignNewDelivery($user)) {
            abort_unless($user->nhanVien?->TrangThai === 'Hoạt động', 403, 'Tài khoản chưa liên kết với nhân viên đang hoạt động.');
        }
    }

    private function requireActiveEmployeeForStaff(?User $user): void
    {
        if ($user && ! EmployeeAssignment::canManageAssignments($user)) {
            abort_unless($user->nhanVien?->TrangThai === 'Hoạt động', 403, 'Tài khoản chưa liên kết với nhân viên đang hoạt động.');
        }
    }

    private function restrictToOwnDelivery(GiaoNhan $delivery, ?User $user): void
    {
        if ($user && ! EmployeeAssignment::canManageAssignments($user)) {
            $this->requireActiveEmployeeForStaff($user);
            abort_unless($user->NhanVienID && (int) $delivery->NhanVienID === (int) $user->NhanVienID, 403);
        }
    }

    private function restrictToOwnBookingDocument(BookingGiaoNhan $document, ?User $user): void
    {
        if ($user && ! EmployeeAssignment::canManageAssignments($user)) {
            $this->requireActiveEmployeeForStaff($user);
            abort_unless($user->NhanVienID && (int) $document->NhanVienID === (int) $user->NhanVienID, 403);
        }
    }

    private function assignableEmployees(): Collection
    {
        return EmployeeAssignment::deliveryCandidates()
            ->orderBy('HoTen')
            ->orderBy('NhanVienID')
            ->get(['NhanVienID', 'HoTen', 'ChucDanh', 'SoDienThoai', 'Email', 'NgayVaoLam']);
    }

    private function bookingIntakeAssignableEmployees(): Collection
    {
        return EmployeeAssignment::bookingIntakeCandidates()
            ->orderBy('HoTen')
            ->orderBy('NhanVienID')
            ->get(['NhanVienID', 'HoTen', 'ChucDanh', 'SoDienThoai', 'Email', 'NgayVaoLam']);
    }
}
