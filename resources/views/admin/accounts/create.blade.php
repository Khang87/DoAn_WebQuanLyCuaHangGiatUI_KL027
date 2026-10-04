@extends('layouts.app')

@section('title', 'Thêm tài khoản - Sky Laundry')
@section('page-title', 'Thêm tài khoản')

@section('content')
@php
    $selectedRole = old('role', 'nhan-vien');
    $customerRoleSlug = \App\Models\VaiTro::slugForName('Khách hàng');
    $isCustomerRole = $selectedRole === $customerRoleSlug;
@endphp
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Thông tin tài khoản mới</h5>
            <a href="{{ route('accounts.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('accounts.store') }}" method="POST">
            @csrf
            <div class="row g-4">
                <div class="col-12">
                    <div class="alert alert-info mb-0">Mỗi tài khoản phải liên kết với đúng một hồ sơ nhân viên hoặc khách hàng đã có.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" placeholder="Email đăng nhập (tối đa 100 ký tự)" maxlength="100" required>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="profilePhone">Số điện thoại hồ sơ</label>
                    <input type="text" class="form-control" id="profilePhone" value="" placeholder="Tự động điền từ hồ sơ đã chọn" readonly>
                    <div class="form-text">Số điện thoại được lấy từ hồ sơ liên kết và không lưu trùng trên tài khoản.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                    <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" placeholder="Nhập mật khẩu" required>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Xác nhận mật khẩu <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" name="password_confirmation" placeholder="Xác nhận mật khẩu" required>
                </div>
                <div class="col-md-6">
                    <x-admin.role-select
                        name="role"
                        id="role"
                        :selected="$selectedRole"
                    />
                </div>
                <div class="col-md-6" id="employee-profile-field" @if($isCustomerRole) hidden @endif>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label mb-0" for="NhanVienID">Hồ sơ nhân viên <span class="text-danger">*</span></label>
                        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#quickCreateEmployeeModal">
                            <i class="bi bi-person-plus me-1"></i>Tạo mới nhân viên
                        </button>
                    </div>
                    <select class="form-select @error('NhanVienID') is-invalid @enderror" id="NhanVienID" name="NhanVienID" @unless($isCustomerRole) required @else disabled @endunless>
                        <option value="">Chọn hồ sơ nhân viên</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->NhanVienID }}" data-phone="{{ $employee->SoDienThoai }}" @selected(old('NhanVienID') == $employee->NhanVienID)>{{ $employee->HoTen }} · {{ $employee->SoDienThoai }}</option>
                        @endforeach
                    </select>
                    @error('NhanVienID')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6" id="customer-profile-field" @unless($isCustomerRole) hidden @endif>
                    <label class="form-label" for="KhachHangID">Hồ sơ khách hàng <span class="text-danger">*</span></label>
                    <select class="form-select @error('KhachHangID') is-invalid @enderror" id="KhachHangID" name="KhachHangID" @if($isCustomerRole) required @else disabled @endif>
                        <option value="">Chọn hồ sơ khách hàng</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->KhachHangID }}" data-phone="{{ $customer->SoDienThoai }}" @selected(old('KhachHangID') == $customer->KhachHangID)>{{ $customer->HoTen }} · {{ $customer->SoDienThoai }}</option>
                        @endforeach
                    </select>
                    @error('KhachHangID')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="reset" class="btn btn-outline-secondary">Làm mới</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Tạo tài khoản
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="quickCreateEmployeeModal" tabindex="-1" aria-labelledby="quickCreateEmployeeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="quickCreateEmployeeForm" data-url="{{ route('accounts.quick-create-employee') }}">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="quickCreateEmployeeModalLabel">Thêm nhanh hồ sơ nhân viên</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="quickEmployeeError" role="alert" aria-live="polite"></div>
                    <div class="mb-3">
                        <label class="form-label" for="quickEmployeeName">Họ và tên <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="quickEmployeeName" name="HoTen" maxlength="100" required>
                        <div class="invalid-feedback" data-error-for="HoTen"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="quickEmployeePhone">Số điện thoại <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control" id="quickEmployeePhone" name="SoDienThoai" maxlength="15" pattern="0[0-9]{9,10}" required>
                        <div class="invalid-feedback" data-error-for="SoDienThoai"></div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="quickEmployeeDepartment">Chi nhánh/Bộ phận</label>
                        <input type="text" class="form-control" id="quickEmployeeDepartment" name="ChucDanh" maxlength="100">
                        <div class="form-text">Thông tin này được lưu vào trường Chức danh trong hồ sơ nhân viên.</div>
                        <div class="invalid-feedback" data-error-for="ChucDanh"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary" id="quickEmployeeSubmit">
                        <i class="bi bi-check-lg me-1"></i>Tạo hồ sơ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var roleSelect = document.getElementById('role');
            var employeeField = document.getElementById('employee-profile-field');
            var customerField = document.getElementById('customer-profile-field');
            var employeeSelect = document.getElementById('NhanVienID');
            var customerSelect = document.getElementById('KhachHangID');
            var profilePhone = document.getElementById('profilePhone');
            var customerRole = @json($customerRoleSlug);

            function updateProfilePhone() {
                var activeSelect = roleSelect.value === customerRole ? customerSelect : employeeSelect;
                var selectedOption = activeSelect.options[activeSelect.selectedIndex];

                profilePhone.value = selectedOption ? (selectedOption.dataset.phone || '') : '';
            }

            function updateProfileFields() {
                var isCustomer = roleSelect.value === customerRole;

                employeeField.hidden = isCustomer;
                employeeSelect.disabled = isCustomer;
                employeeSelect.required = !isCustomer;

                customerField.hidden = !isCustomer;
                customerSelect.disabled = !isCustomer;
                customerSelect.required = isCustomer;

                if (isCustomer) {
                    employeeSelect.value = '';
                } else {
                    customerSelect.value = '';
                }

                updateProfilePhone();
            }

            roleSelect.addEventListener('change', updateProfileFields);
            employeeSelect.addEventListener('change', updateProfilePhone);
            customerSelect.addEventListener('change', updateProfilePhone);
            roleSelect.form.addEventListener('reset', function () {
                window.setTimeout(updateProfileFields, 0);
            });

            updateProfileFields();
        });

        document.addEventListener('DOMContentLoaded', function () {
            var quickEmployeeForm = document.getElementById('quickCreateEmployeeForm');
            var employeeSelect = document.getElementById('NhanVienID');
            var profilePhone = document.getElementById('profilePhone');
            var modalElement = document.getElementById('quickCreateEmployeeModal');
            var submitButton = document.getElementById('quickEmployeeSubmit');
            var errorAlert = document.getElementById('quickEmployeeError');

            function clearQuickEmployeeErrors() {
                errorAlert.classList.add('d-none');
                errorAlert.textContent = '';
                quickEmployeeForm.querySelectorAll('.is-invalid').forEach(function (field) {
                    field.classList.remove('is-invalid');
                });
                quickEmployeeForm.querySelectorAll('[data-error-for]').forEach(function (message) {
                    message.textContent = '';
                });
            }

            quickEmployeeForm.addEventListener('submit', async function (event) {
                event.preventDefault();
                clearQuickEmployeeErrors();
                submitButton.disabled = true;

                try {
                    var response = await fetch(quickEmployeeForm.dataset.url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify(Object.fromEntries(new FormData(quickEmployeeForm))),
                    });
                    var result = await response.json();

                    if (!response.ok) {
                        if (response.status === 422 && result.errors) {
                            Object.keys(result.errors).forEach(function (fieldName) {
                                var field = quickEmployeeForm.elements.namedItem(fieldName);
                                var message = quickEmployeeForm.querySelector('[data-error-for="' + fieldName + '"]');

                                if (field) {
                                    field.classList.add('is-invalid');
                                }
                                if (message) {
                                    message.textContent = result.errors[fieldName][0];
                                }
                            });
                            errorAlert.textContent = result.message || 'Vui lòng kiểm tra lại thông tin hồ sơ.';
                        } else {
                            errorAlert.textContent = result.message || 'Không thể tạo hồ sơ nhân viên. Vui lòng thử lại.';
                        }

                        errorAlert.classList.remove('d-none');
                        return;
                    }

                    var employee = result.employee;
                    var option = document.createElement('option');
                    option.value = employee.NhanVienID;
                    option.textContent = employee.HoTen + ' · ' + employee.SoDienThoai;
                    option.dataset.phone = employee.SoDienThoai;
                    employeeSelect.add(option);
                    employeeSelect.value = String(employee.NhanVienID);
                    profilePhone.value = employee.SoDienThoai;
                    bootstrap.Modal.getOrCreateInstance(modalElement).hide();
                    quickEmployeeForm.reset();
                } catch (error) {
                    errorAlert.textContent = 'Không thể kết nối để tạo hồ sơ nhân viên. Vui lòng thử lại.';
                    errorAlert.classList.remove('d-none');
                } finally {
                    submitButton.disabled = false;
                }
            });

            modalElement.addEventListener('hidden.bs.modal', clearQuickEmployeeErrors);
        });
    </script>
@endpush
