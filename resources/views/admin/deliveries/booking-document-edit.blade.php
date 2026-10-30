@extends('layouts.app')

@section('title', 'Sửa phiếu nhận đồ - Sky Laundry')
@section('page-title', 'Sửa phiếu nhận đồ')

@section('content')
@php
    $booking = $bookingDocument->booking;
    $employee = $bookingDocument->nhanVien;
@endphp

<div class="delivery-form-shell">
    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                <div>
                    <h2 class="h5 mb-1">Cập nhật phiếu nhận đồ</h2>
                    <p class="text-muted mb-0">{{ $displayCode ?? 'BGN-'.$bookingDocument->BookingGiaoNhanID }} · {{ $booking?->MaBooking ?: 'Đặt lịch' }}</p>
                </div>
                <a href="{{ route('deliveries.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Quay lại
                </a>
            </div>

            @if($errors->any())
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0">
                        @foreach($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('deliveries.booking-documents.update', $bookingDocument) }}" method="POST">
                @csrf
                @method('PUT')
                @php
                    $employees = collect([
                        ['NhanVienID' => 9001, 'HoTen' => 'Phạm Văn Cường', 'ChucDanh' => 'Nhân viên', 'SoDienThoai' => null, 'Email' => 'cuong@giatui.vn'],
                        ['NhanVienID' => 9002, 'HoTen' => 'Nguyễn Trường Khang', 'ChucDanh' => 'Nhân viên', 'SoDienThoai' => null, 'Email' => 'khanglmao9@gmail.com'],
                        ['NhanVienID' => 9003, 'HoTen' => 'Võ Anh Khoa', 'ChucDanh' => 'Nhân viên', 'SoDienThoai' => null, 'Email' => 'voanhkhoa010203@gmail.com'],
                        ['NhanVienID' => 9004, 'HoTen' => 'Trần Văn Bìnhh', 'ChucDanh' => 'Nhân viên', 'SoDienThoai' => null, 'Email' => 'binh@giatui.vn'],
                    ]);
                @endphp
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="booking_display">Đặt lịch</label>
                        <input class="form-control" id="booking_display" value="{{ $booking?->MaBooking ?: '—' }}" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="fulfillment_display">Hình thức nhận đồ</label>
                        <input class="form-control" id="fulfillment_display" value="{{ $bookingDocument->HinhThuc }}" readonly>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="employee_select">Nhân viên phụ trách</label>
                        @if($canAssignEmployee)
                        @if($canAssignEmployee)
                            <div class="delivery-employee-picker" data-employee-picker>
                                <button class="form-select text-start text-truncate" id="employee_select" type="button"
                                        data-employee-toggle aria-expanded="false" aria-haspopup="listbox"
                                        aria-controls="delivery-employee-dropdown">
                                    <span data-selected-employee-name>{{ $employee?->HoTen ?: 'Chọn nhân viên' }}</span>
                                </button>
                                <div class="dropdown-menu w-100 p-2" id="delivery-employee-dropdown" data-employee-dropdown hidden>
                                    <label class="visually-hidden" for="employee_search">Tìm theo tên nhân viên hoặc chức danh</label>
                                    <input class="form-control" id="employee_search" type="search"
                                           autocomplete="off" data-employee-search
                                           placeholder="Tìm theo tên nhân viên hoặc chức danh..." aria-controls="delivery-employee-options"
                                           aria-autocomplete="list">
                                    <div class="delivery-employee-dropdown__results" id="delivery-employee-options"
                                         role="listbox" aria-label="Nhân viên có thể được phân công" data-employee-options>
                                        @foreach($employees as $candidate)
                                            <button class="delivery-employee-option" type="button" role="option"
                                                    aria-selected="@selected((int) $employee?->NhanVienID === (int) $candidate['NhanVienID])" data-employee-option
                                                    data-employee-id="{{ $candidate['NhanVienID'] }}"
                                                    data-employee-name="{{ $candidate['HoTen'] }}"
                                                    data-employee-description="{{ $candidate['ChucDanh'] }}"
                                                    data-employee-search-text="{{ $candidate['HoTen'] }} {{ $candidate['ChucDanh'] }}">
                                                    <span>
                                                        <span class="d-block">{{ $candidate['HoTen'] }}</span>
                                                        <span class="small text-muted">{{ $candidate['ChucDanh'] }}</span>
                                                    </span>
                                                    <i class="bi bi-check2 text-primary" aria-hidden="true" hidden data-selected-indicator></i>
                                            </button>
                                        @endforeach
                                    </div>
                                    <div class="small text-muted px-2 py-2" data-employee-empty hidden>Không tìm thấy nhân viên phù hợp</div>
                                </div>
                                <input type="hidden" id="employee_id" name="employee_id" value="{{ $employee?->NhanVienID }}" data-employee-id>
                                <input type="hidden" name="employee_search" value="{{ $employee?->HoTen }}" data-selected-employee-search>
                                @error('employee_id')<div class="text-danger small mt-1" role="alert">{{ $message }}</div>@enderror
                            </div>
                        @else
                            <input class="form-control" id="employee_id" value="{{ $employee?->HoTen ?: 'Chưa phân công' }}" readonly>
                        @endif
                    </div>

                    @if($bookingDocument->HinhThuc === 'Tại nhà')
                        <div class="col-12">
                            <label class="form-label" for="address">Địa chỉ nhận đồ <span class="text-danger">*</span></label>
                            <input class="form-control" id="address" name="address" value="{{ old('address', $bookingDocument->DiaChi) }}" required maxlength="255">
                            @error('address')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                    @endif

                    <div class="col-md-6">
                        <label class="form-label" for="pickup_date">Ngày dự kiến</label>
                        <input type="date" class="form-control" id="pickup_date" name="pickup_date"
                               value="{{ old('pickup_date', $bookingDocument->ThoiGianDuKien?->format('Y-m-d')) }}">
                        @error('pickup_date')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="pickup_time">Giờ dự kiến</label>
                        <input type="time" class="form-control" id="pickup_time" name="pickup_time"
                               value="{{ old('pickup_time', $bookingDocument->ThoiGianDuKien?->format('H:i')) }}">
                        @error('pickup_time')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Cập nhật</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

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

@push('scripts')
    <script>
        (function () {
            const employeePicker = document.querySelector('[data-employee-picker]');
            if (!employeePicker) {
                return;
            }

            const employeeSearch = employeePicker.querySelector('[data-employee-search]');
            const employeeId = employeePicker.querySelector('input[name="employee_id"][data-employee-id]');
            const selectedEmployeeSearch = employeePicker.querySelector('[data-selected-employee-search]');
            const dropdown = employeePicker.querySelector('[data-employee-dropdown]');
            const toggle = employeePicker.querySelector('[data-employee-toggle]');
            const emptyMessage = employeePicker.querySelector('[data-employee-empty]');
            const selectedName = employeePicker.querySelector('[data-selected-employee-name]');
            const options = [...employeePicker.querySelectorAll('[data-employee-option]')];
            let activeOptionIndex = -1;

            const normalize = value => value
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/đ/g, 'd')
                .replace(/Đ/g, 'D')
                .toLocaleLowerCase('vi');

            const visibleOptions = () => options.filter(option => !option.hidden);

            const filterOptions = () => {
                const query = normalize(employeeSearch.value.trim());
                options.forEach(option => {
                    option.hidden = !normalize(option.dataset.employeeSearchText || '').includes(query);
                });
                emptyMessage.hidden = visibleOptions().length > 0;
                activeOptionIndex = -1;
                options.forEach(option => option.removeAttribute('tabindex'));
            };

            const closeDropdown = ({ restoreFocus = false } = {}) => {
                dropdown.hidden = true;
                dropdown.classList.remove('show');
                toggle.setAttribute('aria-expanded', 'false');
                employeeSearch.value = '';
                filterOptions();
                if (restoreFocus) {
                    toggle.focus();
                }
            };

            const openDropdown = () => {
                dropdown.hidden = false;
                dropdown.classList.add('show');
                toggle.setAttribute('aria-expanded', 'true');
                employeeSearch.value = '';
                filterOptions();
                employeeSearch.focus();
            };

            const chooseEmployee = option => {
                employeeId.value = option.dataset.employeeId;
                selectedName.textContent = option.dataset.employeeName;
                selectedEmployeeSearch.value = option.dataset.employeeName;
                options.forEach(item => {
                    const isSelected = item === option;
                    item.setAttribute('aria-selected', String(isSelected));
                    item.querySelector('[data-selected-indicator]').hidden = !isSelected;
                });
                closeDropdown({ restoreFocus: true });
            };

            const focusOption = index => {
                const visible = visibleOptions();
                if (visible.length === 0) {
                    return;
                }
                activeOptionIndex = (index + visible.length) % visible.length;
                visible[activeOptionIndex].focus();
            };

            toggle.addEventListener('click', () => {
                if (dropdown.hidden) {
                    openDropdown();
                } else {
                    closeDropdown();
                }
            });

            employeeSearch.addEventListener('input', filterOptions);
            employeeSearch.addEventListener('keydown', event => {
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    focusOption(0);
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    closeDropdown({ restoreFocus: true });
                } else if (event.key === 'Enter') {
                    event.preventDefault();
                    if (visibleOptions().length === 1) {
                        chooseEmployee(visibleOptions()[0]);
                    }
                }
            });

            options.forEach(option => {
                option.addEventListener('click', () => chooseEmployee(option));
                option.addEventListener('keydown', event => {
                    const visible = visibleOptions();
                    const index = visible.indexOf(option);
                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        focusOption(index + 1);
                    } else if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        if (index === 0) {
                            employeeSearch.focus();
                        } else {
                            focusOption(index - 1);
                        }
                    } else if (event.key === 'Escape') {
                        event.preventDefault();
                        closeDropdown({ restoreFocus: true });
                    }
                });
            });

            employeePicker.closest('form').addEventListener('submit', () => {
                if (!dropdown.hidden && employeeSearch.value.trim()) {
                    employeeId.value = '';
                }
            });
            document.addEventListener('pointerdown', event => {
                if (!dropdown.hidden && !employeePicker.contains(event.target)) {
                    if (event.target.closest('button[type="submit"], input[type="submit"]')) {
                        return;
                    }
                    closeDropdown();
                }
            });

            filterOptions();
        })();
    </script>
@endpush
