@extends('layouts.app')
@section('title', 'Sửa phiếu '.($delivery->LoaiGiaoNhan === 'NHAN_DO' ? 'nhận' : 'giao').' đồ - Sky Laundry')
@section('page-title', 'Sửa phiếu '.($delivery->LoaiGiaoNhan === 'NHAN_DO' ? 'nhận' : 'giao').' đồ')

@push('styles')
<style>
    .delivery-fixed-field {
        background-color: #f5f5f5;
        cursor: not-allowed;
    }

    .delivery-employee-picker { position: relative; z-index: 1021; }
    .delivery-employee-dropdown__results { max-height: min(18rem, 45vh); margin-top: 0.5rem; overflow-y: auto; }
    .delivery-employee-option {
        display: flex; width: 100%; align-items: center; justify-content: space-between; gap: 0.75rem;
        padding: 0.5rem 0.75rem; border: 0; border-radius: var(--bs-border-radius);
        background: transparent; color: var(--bs-body-color); text-align: left;
    }
    .delivery-employee-option:hover,
    .delivery-employee-option:focus-visible,
    .delivery-employee-option[aria-selected="true"] {
        outline: none; background: var(--bs-primary-bg-subtle); color: var(--bs-primary-text-emphasis);
    }
    .delivery-employee-option:focus-visible { box-shadow: inset 0 0 0 2px var(--bs-primary); }
</style>
@endpush

@section('content')
@php
    $order = $delivery->donHang;
    $orderLabel = $order
        ? $order->MaDonHang.' · '.($order->khachHang?->HoTen ?: 'Khách hàng')
        : 'Không tìm thấy thông tin đơn hàng';
    $methodLabel = match ($delivery->LoaiGiaoNhan) {
        'NHAN_DO' => 'Nhận đồ',
        'GIAO_DO' => 'Giao đồ',
        default => '—',
    };
    $deliveryLegLabel = $delivery->LoaiGiaoNhan === 'NHAN_DO' ? 'nhận đồ' : 'giao đồ';
    $assignedEmployeeName = $assignedEmployee?->HoTen ?? $delivery->nhanVien?->HoTen ?? 'Chưa phân công';
    $employeeOptions = $employees ?? collect();
    $employeeNameCounts = $employeeOptions->countBy(fn ($employee) => mb_strtolower(trim((string) $employee->HoTen)));
    $selectedEmployee = $employeeOptions->firstWhere('NhanVienID', (int) old('employee_id', $delivery->NhanVienID));
    $statusOptions ??= \App\Enums\DeliveryStatus::options();
@endphp
<div class="delivery-form-shell"><div class="card"><div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h2 class="h5 mb-0">Cập nhật phiếu {{ $delivery->LoaiGiaoNhan === 'NHAN_DO' ? 'nhận' : 'giao' }} đồ</h2>
        <a href="{{ \App\Support\OrderWorkflow::listBackUrl(route('deliveries.index')) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Quay lại
        </a>
    </div>
    <form action="{{ route('deliveries.update', $delivery) }}" method="POST">@csrf @method('PUT')
        <input type="hidden" name="order_id" value="{{ $delivery->DonHangID }}">
        <input type="hidden" name="method" value="{{ strtolower($delivery->LoaiGiaoNhan) }}">
        <input type="hidden" name="fulfillment" value="{{ $delivery->HinhThuc }}">
        @if($errors->any())
            <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
        @endif
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="order_display">Đơn hàng <span class="text-danger">*</span></label>
                <input class="form-control delivery-fixed-field" id="order_display" value="{{ $orderLabel }}" readonly aria-readonly="true">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="employee_id">Nhân viên phụ trách {{ $deliveryLegLabel }} <span class="text-danger">*</span></label>
                @if($canAssignEmployee ?? true)
                    <div class="delivery-employee-picker" data-employee-picker>
                        <button class="form-select text-start text-truncate" id="employee_select" type="button"
                                data-employee-toggle aria-expanded="false" aria-haspopup="listbox"
                                aria-controls="delivery-employee-dropdown">
                            <span data-selected-employee-name>{{ $selectedEmployee?->HoTen ?: 'Chọn nhân viên phụ trách' }}</span>
                        </button>
                        <div class="dropdown-menu w-100 p-2" id="delivery-employee-dropdown" data-employee-dropdown hidden>
                            <label class="visually-hidden" for="employee_search">Tìm theo tên nhân viên hoặc chức danh</label>
                            <input class="form-control" id="employee_search" type="search" autocomplete="off"
                                   data-employee-search placeholder="Tìm theo tên nhân viên hoặc chức danh..."
                                   aria-controls="delivery-employee-options" aria-autocomplete="list">
                            <div class="delivery-employee-dropdown__results" id="delivery-employee-options"
                                 role="listbox" aria-label="Nhân viên có thể được phân công" data-employee-options>
                                @foreach($employeeOptions as $employee)
                                    @php
                                        $employeeNameKey = mb_strtolower(trim((string) $employee->HoTen));
                                        $employeeDescription = (string) ($employee->ChucDanh ?? '');
                                        $employeeSelected = $selectedEmployee
                                            && (int) $selectedEmployee->NhanVienID === (int) $employee->NhanVienID;
                                    @endphp
                                    <button class="delivery-employee-option" type="button" role="option"
                                            aria-selected="{{ $employeeSelected ? 'true' : 'false' }}"
                                            data-employee-option data-employee-id="{{ $employee->NhanVienID }}"
                                            data-employee-name="{{ $employee->HoTen }}"
                                            data-employee-description="{{ $employeeDescription }}"
                                            data-employee-search-text="{{ $employee->HoTen }} {{ $employeeDescription }}">
                                        <span>
                                            <span class="d-block">@if((int) $employee->NhanVienID === (int) (auth()->user()?->NhanVienID ?? 0))Bản thân tôi — @endif{{ $employee->HoTen }}{{ $employeeDescription ? ' · '.$employeeDescription : '' }}@if(($employeeNameCounts[$employeeNameKey] ?? 0) > 1) · {{ $employee->SoDienThoai ?: ($employee->Email ?: 'Mã NV '.$employee->NhanVienID) }}@endif</span>
                                        </span>
                                        <i class="bi bi-check2 text-primary" aria-hidden="true" hidden data-selected-indicator></i>
                                    </button>
                                @endforeach
                            </div>
                            <div class="small text-muted px-2 py-2" data-employee-empty hidden>Không tìm thấy nhân viên phù hợp</div>
                        </div>
                        <input type="hidden" id="employee_id" name="employee_id"
                               value="{{ $selectedEmployee?->NhanVienID }}" data-employee-id>
                        <input type="hidden" name="employee_search"
                               value="{{ old('employee_search', $selectedEmployee?->HoTen) }}" data-selected-employee-search>
                    </div>
                    @error('employee_id')<div class="text-danger small mt-1" role="alert">{{ $message }}</div>@enderror
                @else
                    <input class="form-control delivery-fixed-field" id="employee_id" value="{{ $assignedEmployeeName }}" readonly aria-readonly="true">
                    <input type="hidden" name="employee_id" value="{{ $assignedEmployee->NhanVienID }}">
                @endif
            </div>
            <div class="col-12">
                <label class="form-label" for="method_display">Loại giao nhận <span class="text-danger ms-1">*</span></label>
                <input class="form-control delivery-fixed-field" id="method_display" value="{{ $methodLabel }}" readonly aria-readonly="true">
            </div>
            <div class="col-12">
                <label class="form-label" for="fulfillment_display">Hình thức nhân viên {{ $deliveryLegLabel }}</label>
                <input class="form-control delivery-fixed-field" id="fulfillment_display" value="{{ $delivery->HinhThuc }}" readonly aria-readonly="true">
            </div>
            <div class="col-12"><label class="form-label" for="address">Địa chỉ nhân viên {{ $deliveryLegLabel }} (bắt buộc khi tại nhà)</label><input type="text" class="form-control" id="address" name="address" value="{{ old('address', $delivery->DiaChi) }}" @required(old('fulfillment', $delivery->HinhThuc) === 'Tại nhà') @disabled(old('fulfillment', $delivery->HinhThuc) === 'Tại cửa hàng')></div>
            <div class="col-md-6"><label class="form-label" for="pickup_date">Ngày {{ $deliveryLegLabel }} dự kiến</label><input type="date" class="form-control" id="pickup_date" name="pickup_date" aria-describedby="schedule-help" value="{{ old('pickup_date', $delivery->ThoiGianDuKien?->format('Y-m-d')) }}">@error('pickup_date')<div class="text-danger small">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label" for="pickup_time">Giờ {{ $deliveryLegLabel }} dự kiến</label><input type="time" class="form-control" id="pickup_time" name="pickup_time" aria-describedby="schedule-help" value="{{ old('pickup_time', $delivery->ThoiGianDuKien?->format('H:i')) }}">@error('pickup_time')<div class="text-danger small">{{ $message }}</div>@enderror</div>
            <div class="col-12"><label class="form-label" for="status">Trạng thái</label><x-admin.status-select name="status" id="status" :options="$statusOptions" :selected="\App\Enums\DeliveryStatus::parseForLeg($delivery->TrangThai, $delivery->LoaiGiaoNhan)->value" class="form-select" /></div>
            <div class="col-12 form-text" id="schedule-help" aria-live="polite">Phiếu trả đang chờ có thể chưa đặt lịch. Khi đặt lịch, hãy nhập đủ ngày và giờ.</div>
            <div class="col-12"><label class="form-label" for="notes">Ghi chú</label><textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes', $delivery->GhiChu) }}</textarea></div>
        </div>
        <div class="d-flex justify-content-end mt-4"><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Cập nhật</button></div>
    </form>
</div></div></div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/delivery-schedule.js') }}"></script>
@endpush
