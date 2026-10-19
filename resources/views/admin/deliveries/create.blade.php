@extends('layouts.app')
@section('title', 'Tạo lịch giao Nhận - Sky Laundry')
@section('page-title', 'Tạo lịch giao nhận')
@push('styles')
<style>
    .delivery-employee-picker {
        position: relative;
        z-index: 1021;
    }

    .delivery-employee-dropdown__results {
        max-height: min(18rem, 45vh);
        margin-top: 0.5rem;
        overflow-y: auto;
    }

    .delivery-employee-option {
        display: flex;
        width: 100%;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.5rem 0.75rem;
        border: 0;
        border-radius: var(--bs-border-radius);
        background: transparent;
        color: var(--bs-body-color);
        text-align: left;
    }

    .delivery-employee-option:hover,
    .delivery-employee-option:focus-visible,
    .delivery-employee-option[aria-selected="true"] {
        outline: none;
        background: var(--bs-primary-bg-subtle);
        color: var(--bs-primary-text-emphasis);
    }

    .delivery-employee-option:focus-visible {
        box-shadow: inset 0 0 0 2px var(--bs-primary);
    }
</style>
@endpush
@section('content')
@php
    $orderOptions = $orders ?? \App\Models\DonHang::orderByDesc('NgayTao')->get();
    $employeeOptions = $employees ?? \App\Models\NhanVien::where('TrangThai', 'Hoạt động')->orderBy('HoTen')->get();
    $selectedEmployee ??= null;
    $employeeNameCounts = $employeeOptions->countBy(fn ($employee) => mb_strtolower(trim((string) $employee->HoTen)));
    $employeeDuplicateIndexes = [];
    $selectedOrderId ??= old('order_id');
    $selectedMethod ??= old('method', 'nhan_do');
    $selectedFulfillment ??= old('fulfillment', 'Tại nhà');
    $selectedAddress ??= old('address');
    $statusOptions ??= \App\Enums\DeliveryStatus::options();
@endphp
<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-4"><h5 class="mb-0">Thông tin lịch giao nhận</h5><a href="{{ route('deliveries.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Quay lại</a></div>
    <form action="{{ route('deliveries.store') }}" method="POST" data-booking-prefill>@csrf
        @if($errors->any())
            <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
        @endif
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="order_id">Đơn hàng <span class="text-danger">*</span></label><select class="form-select" id="order_id" name="order_id" required><option value="">Chọn đơn hàng</option>@foreach($orderOptions as $order)<option value="{{ $order->DonHangID }}" data-booking-id="{{ $order->booking?->BookingID }}" data-receive-method="{{ $order->booking?->HinhThucNhanDo }}" data-receive-address="{{ $order->booking?->DiaChiNhan }}" data-return-method="{{ $order->booking?->HinhThucTraDo }}" data-return-address="{{ $order->booking?->DiaChiTra }}" data-pickup-date="{{ $order->booking?->NgayHen?->format('Y-m-d') }}" data-pickup-time="{{ $order->booking?->GioHen?->format('H:i') }}" @selected($selectedOrderId == $order->DonHangID)>{{ $order->MaDonHang }} · {{ $order->khachHang?->HoTen ?: 'Khách hàng' }}</option>@endforeach</select></div>
            <div class="col-md-6">
                <label class="form-label" for="employee_select">Nhân viên phụ trách <span class="text-danger">*</span></label>
                @if($canAssignEmployee ?? true)
                    <div class="delivery-employee-picker" data-employee-picker>
                        <button class="form-select text-start text-truncate" id="employee_select" type="button"
                                data-employee-toggle aria-expanded="false" aria-haspopup="listbox"
                                aria-controls="delivery-employee-dropdown">
                            <span data-selected-employee-name>{{ $selectedEmployee?->HoTen ?: 'Chọn nhân viên phụ trách' }}</span>
                        </button>
                        <div class="dropdown-menu w-100 p-2" id="delivery-employee-dropdown" data-employee-dropdown hidden>
                            <label class="visually-hidden" for="employee_search">Tìm theo tên nhân viên</label>
                            <input class="form-control" id="employee_search" type="search"
                                   autocomplete="off" data-employee-search
                                   placeholder="Tìm theo tên nhân viên..." aria-controls="delivery-employee-options"
                                   aria-autocomplete="list">
                            <div class="delivery-employee-dropdown__results" id="delivery-employee-options"
                                 role="listbox" aria-label="Nhân viên có thể được phân công" data-employee-options>
                                @foreach($employeeOptions as $employee)
                                    @php
                                        $employeeNameKey = mb_strtolower(trim((string) $employee->HoTen));
                                        $employeeDisambiguators = array_filter([
                                            $employee->ChucDanh,
                                            $employee->SoDienThoai,
                                            $employee->Email,
                                            $employee->NgayVaoLam?->format('d/m/Y'),
                                        ]);
                                        $employeeDescription = implode(' · ', $employeeDisambiguators);
                                        if (($employeeNameCounts[$employeeNameKey] ?? 0) > 1) {
                                            $employeeDuplicateIndexes[$employeeNameKey] = ($employeeDuplicateIndexes[$employeeNameKey] ?? 0) + 1;
                                            if ($employeeDescription === '') {
                                                $employeeDescription = 'Hồ sơ cùng tên · lựa chọn '.$employeeDuplicateIndexes[$employeeNameKey];
                                            }
                                        }
                                        $employeeSelected = $selectedEmployee
                                            && (int) $selectedEmployee->NhanVienID === (int) $employee->NhanVienID;
                                    @endphp
                                    <button class="delivery-employee-option" type="button" role="option"
                                            aria-selected="{{ $employeeSelected ? 'true' : 'false' }}"
                                            data-employee-option
                                            data-employee-id="{{ $employee->NhanVienID }}"
                                            data-employee-name="{{ $employee->HoTen }}"
                                            data-employee-description="{{ $employeeDescription }}"
                                            data-employee-search-text="{{ $employee->HoTen }} {{ $employeeDescription }}">
                                        <span>
                                            <span class="d-block">{{ $employee->HoTen }}</span>
                                            @if(($employeeNameCounts[$employeeNameKey] ?? 0) > 1 || $employeeDescription !== '')
                                                <span class="small text-muted">{{ $employeeDescription ?: 'Thông tin nhân viên chưa bổ sung' }}</span>
                                            @endif
                                        </span>
                                        <i class="bi bi-check2 text-primary" aria-hidden="true" @if(! $employeeSelected) hidden @endif data-selected-indicator></i>
                                    </button>
                                @endforeach
                            </div>
                            <div class="small text-muted px-2 py-2" data-employee-empty hidden>Không tìm thấy nhân viên phù hợp</div>
                        </div>
                        <input type="hidden" id="employee_id" name="employee_id"
                               value="{{ $selectedEmployee?->NhanVienID }}" data-employee-id>
                        <input type="hidden" name="employee_search"
                               value="{{ old('employee_search', $selectedEmployee?->HoTen) }}" data-selected-employee-search>
                        @if($errors->has('employee_id'))
                            <div class="text-danger small mt-1" role="alert">{{ $errors->first('employee_id') }}</div>
                        @elseif(! $selectedEmployee)
                            <div class="form-text text-danger">Tài khoản chưa có nhân viên hợp lệ được liên kết. Vui lòng chọn nhân viên phụ trách.</div>
                        @endif
                    </div>
                @else
                    <input class="form-select" id="employee_select" value="{{ $assignedEmployee?->HoTen ?: 'Chưa chọn nhân viên' }}" readonly aria-readonly="true">
                    <input type="hidden" name="employee_id" value="{{ $assignedEmployee?->NhanVienID }}">
                    @if($errors->has('employee_id'))
                        <div class="text-danger small mt-1" role="alert">{{ $errors->first('employee_id') }}</div>
                    @elseif(! $assignedEmployee)
                        <div class="form-text text-danger">Tài khoản chưa có nhân viên hợp lệ được liên kết.</div>
                    @endif
                @endif
            </div>
            <div class="col-12"><label class="form-label" for="method">Loại giao nhận <span class="text-danger ms-1">*</span></label><select class="form-select" id="method" name="method" required><option value="nhan_do" @selected($selectedMethod === 'nhan_do')>Nhận đồ</option><option value="giao_do" @selected($selectedMethod === 'giao_do')>Giao đồ</option></select></div>
            <div class="col-12"><label class="form-label" for="fulfillment">Hình thức</label><select class="form-select" id="fulfillment" name="fulfillment" required><option value="Tại cửa hàng" @selected($selectedFulfillment === 'Tại cửa hàng')>Tại cửa hàng</option><option value="Tại nhà" @selected($selectedFulfillment === 'Tại nhà')>Tại nhà</option></select></div>
            <div class="col-12"><label class="form-label" for="address">Địa chỉ (bắt buộc khi tại nhà)</label><input type="text" class="form-control" id="address" name="address" value="{{ $selectedAddress }}" placeholder="Nhập địa chỉ lấy/giao đồ" @required($selectedFulfillment === 'Tại nhà') @disabled($selectedFulfillment === 'Tại cửa hàng')></div>
            <div class="col-md-6"><label class="form-label" for="pickup_date">Ngày giao nhận</label><input type="date" class="form-control" id="pickup_date" name="pickup_date" aria-describedby="schedule-help" value="{{ old('pickup_date') }}">@error('pickup_date')<div class="text-danger small">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label" for="pickup_time">Giờ giao nhận</label><input type="time" class="form-control" id="pickup_time" name="pickup_time" aria-describedby="schedule-help" value="{{ old('pickup_time') }}">@error('pickup_time')<div class="text-danger small">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label" for="status">Trạng thái</label><x-admin.status-select name="status" id="status" :options="$statusOptions" selected="pending" class="form-select" /></div>
            <div class="col-12 form-text" id="schedule-help" aria-live="polite">Phiếu trả đang chờ có thể chưa đặt lịch. Khi đặt lịch, hãy nhập đủ ngày và giờ.</div>
            <div class="col-12 form-text">Khi chọn đơn có Booking, hình thức, địa chỉ và lịch nhận (nếu có) sẽ được điền sẵn; bạn có thể điều chỉnh trước khi lưu.</div>
            <div class="col-12"><label class="form-label" for="notes">Ghi chú</label><textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes') }}</textarea></div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('deliveries.index') }}" class="btn btn-outline-secondary">Hủy</a><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Lưu lịch giao nhận</button></div>
    </form>
</div></div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/delivery-schedule.js') }}"></script>
@endpush
