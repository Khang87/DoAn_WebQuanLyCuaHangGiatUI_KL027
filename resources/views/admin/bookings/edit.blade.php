@extends('layouts.app')

@section('title', ($inspectionMode ?? false) ? 'Kiểm tra thực tế - Sky Laundry' : 'Chỉnh sửa đặt lịch - Sky Laundry')
@section('page-title', ($inspectionMode ?? false) ? 'Kiểm tra thực tế Booking' : 'Chỉnh sửa đặt lịch')

@push('styles')
<style>
    #booking-edit-form .form-control:disabled,
    #booking-edit-form .form-select:disabled {
        background-color: #e9ecef !important;
        color: #6c757d !important;
        cursor: not-allowed;
        opacity: 1;
    }
</style>
@endpush

@section('content')
@php
    $inspectionMode = $inspectionMode ?? false;
    $customer = $booking->khachHang;
    $customerAccount = $customer?->taiKhoan;
    $customerName = $customer?->HoTen ?: $customerAccount?->TenDangNhap;
    $customerPhone = $customer?->SoDienThoai ?: $customerAccount?->SoDienThoai;
    $customerEmail = $customer?->Email ?: $customerAccount?->Email;
@endphp
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4"><h5 class="mb-0">{{ $inspectionMode ? 'Kiểm tra thực tế trước khi tạo đơn' : 'Chỉnh sửa đặt lịch' }}</h5><a href="{{ route('bookings.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Quay lại</a></div>
        <form id="booking-edit-form" action="{{ route($inspectionMode ? 'bookings.confirm' : 'bookings.update', $booking) }}" method="POST">
            @csrf @if(! $inspectionMode) @method('PUT') @endif
            <div class="row g-4">
                <input type="hidden" name="customer_id" value="{{ $booking->KhachHangID }}">
                <div class="col-md-4">
                    <label class="form-label" for="booking-customer-name">Khách hàng</label>
                    <input class="form-control bg-light text-muted" id="booking-customer-name" value="{{ $customerName ?: 'Không tìm thấy thông tin khách hàng' }}" disabled>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="booking-customer-phone">Số điện thoại</label>
                    <input class="form-control bg-light text-muted" id="booking-customer-phone" value="{{ $customerPhone ?: '—' }}" disabled>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="booking-customer-email">Email</label>
                    <input class="form-control bg-light text-muted" id="booking-customer-email" value="{{ $customerEmail ?: '—' }}" disabled>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="booking-staff-id">
                        Nhân viên phụ trách
                        @if(! $responsibleEmployeeLocked)
                            <span class="text-danger d-none" data-staff-required-indicator>*</span>
                        @endif
                    </label>
                    @if($responsibleEmployeeLocked)
                        <input type="hidden" name="staff_id" value="{{ $booking->NhanVienID }}">
                    @endif
                    <select
                        class="form-select @error('staff_id') is-invalid @enderror"
                        id="booking-staff-id"
                        name="staff_id"
                        @disabled($responsibleEmployeeLocked)
                    >
                        <option value="">Chọn nhân viên phụ trách</option>
                        @foreach($employees as $employee)
                            <option
                                value="{{ $employee->NhanVienID }}"
                                @selected(($responsibleEmployeeLocked ? $booking->NhanVienID : old('staff_id', $booking->NhanVienID ?? '')) == $employee->NhanVienID)
                            >{{ $employee->HoTen }}</option>
                        @endforeach
                    </select>
                    @error('staff_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @if($responsibleEmployeeLocked)
                        <div class="form-text">Nhân viên phụ trách đã được khóa vì Booking đã chuyển thành đơn hàng.</div>
                    @endif
                </div>
                <div class="col-md-6"><label class="form-label">Hình thức nhận đồ <span class="text-danger ms-1">*</span></label><select class="form-select @error('method') is-invalid @enderror" name="method" id="booking-method" required>@foreach(\App\Enums\ReceiveMethod::options() as $value => $label)<option value="{{ $value }}" @selected(old('method', $booking->HinhThucNhanDo) === $value)>{{ $label }}</option>@endforeach</select>@error('method')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label">Địa chỉ nhận đồ</label><input type="text" class="form-control @error('address') is-invalid @enderror" name="address" value="{{ old('address', $booking->DiaChiNhan) }}" maxlength="255" @required(old('method', $booking->HinhThucNhanDo) === \App\Enums\ReceiveMethod::Home->value)>@error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6">
                    <label class="form-label" for="booking-return-method">Hình thức trả đồ <span class="text-danger">*</span></label>
                    <select class="form-select @error('return_method') is-invalid @enderror" name="return_method" id="booking-return-method" required>
                        <option value="">Chọn hình thức trả đồ</option>
                        @foreach(\App\Enums\ReturnMethod::options() as $value => $label)
                            <option value="{{ $value }}" @selected(old('return_method', $booking->HinhThucTraDo) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('return_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="booking-return-address">Địa chỉ trả đồ</label>
                    <input type="text" id="booking-return-address" class="form-control @error('return_address') is-invalid @enderror" name="return_address" value="{{ old('return_address', $booking->DiaChiTra) }}" maxlength="255" @required(old('return_method', $booking->HinhThucTraDo) === \App\Enums\ReturnMethod::Home->value)>
                    @error('return_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6"><label class="form-label">Ngày hẹn <span class="text-danger ms-1">*</span></label><input type="date" class="form-control @error('scheduled_date') is-invalid @enderror" name="scheduled_date" value="{{ old('scheduled_date', $booking->NgayHen?->format('Y-m-d')) }}" required>@error('scheduled_date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label">Giờ hẹn <span class="text-danger ms-1">*</span></label><input type="time" class="form-control @error('scheduled_time') is-invalid @enderror" name="scheduled_time" value="{{ old('scheduled_time', $booking->GioHen?->format('H:i')) }}" required>@error('scheduled_time')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                @if($errors->any())
                    <div class="col-12"><div class="alert alert-danger mb-0">@foreach($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div></div>
                @endif
                <div class="col-12"><hr class="my-1"><div class="d-flex justify-content-between align-items-center"><div><h6 class="mb-1">{{ $inspectionMode ? 'Đồ và dịch vụ thực tế' : 'Dịch vụ dự kiến' }}</h6><small class="text-muted">Có thể thêm nhiều dòng. Đơn giá và thành tiền được tính lại từ bảng giá hiệu lực khi lưu.</small></div><button type="button" class="btn btn-outline-primary btn-sm" id="add-booking-item"><i class="bi bi-plus-lg me-1"></i>Thêm dòng</button></div></div>
                @php
                    $bookingItems = old('items', $booking->chiTietBookings->map(fn ($item) => $item->only(['DichVuID', 'LoaiDoGiatID', 'DonViTinhID', 'SoLuong', 'KhoiLuong', 'GhiChu']))->all());
                    if ($bookingItems === []) {
                        $bookingItems = [[]];
                    }
                @endphp
                <div class="col-12" id="booking-items">
                    @foreach($bookingItems as $index => $item)
                        @php
                            $quantityValue = $item['SoLuong'] ?? '';
                            if (is_numeric($quantityValue) && floor((float) $quantityValue) === (float) $quantityValue) {
                                $quantityValue = (string) (int) $quantityValue;
                            }
                        @endphp
                        @php
                            $selectedService = $services->firstWhere('DichVuID', $item['DichVuID'] ?? null);
                            $selectedCategoryId = $item['service_category_id'] ?? $selectedService?->LoaiDichVuID;
                        @endphp
                        <div class="booking-item border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-3"><strong>Dòng dịch vụ</strong><button type="button" class="btn btn-outline-danger btn-sm remove-booking-item">Xóa dòng</button></div>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Danh mục dịch vụ</label>
                                    <select class="form-select booking-service-category" name="items[{{ $index }}][service_category_id]">
                                        <option value="">Chọn danh mục</option>
                                        @foreach($serviceCategories as $category)
                                            <option value="{{ $category->LoaiDichVuID }}" @selected((string) $selectedCategoryId === (string) $category->LoaiDichVuID)>{{ $category->TenLoaiDichVu }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Dịch vụ</label>
                                    <select class="form-select booking-service" name="items[{{ $index }}][DichVuID]">
                                        <option value="">Chọn dịch vụ</option>
                                        @foreach($services as $service)
                                            <option value="{{ $service->DichVuID }}" @selected((string) ($item['DichVuID'] ?? '') === (string) $service->DichVuID)>{{ $service->TenDichVu }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text booking-service-empty d-none" aria-live="polite"></div>
                                </div>
                                <div class="col-md-3"><label class="form-label">Loại đồ giặt</label><select class="form-select booking-garment" name="items[{{ $index }}][LoaiDoGiatID]"><option value="">Chọn loại đồ</option>@foreach($garments as $garment)<option value="{{ $garment->LoaiDoGiatID }}" @selected((string) ($item['LoaiDoGiatID'] ?? '') === (string) $garment->LoaiDoGiatID)>{{ $garment->TenLoaiDoGiat }}</option>@endforeach</select></div>
                                <div class="col-md-3"><label class="form-label">Đơn vị tính</label><select class="form-select booking-unit" name="items[{{ $index }}][DonViTinhID]"><option value="">Chọn đơn vị</option>@foreach($units as $unit)<option value="{{ $unit->DonViTinhID }}" data-unit="{{ $unit->KyHieu ?: $unit->TenDonViTinh }}" @selected((string) ($item['DonViTinhID'] ?? '') === (string) $unit->DonViTinhID)>{{ $unit->TenDonViTinh }}{{ $unit->KyHieu ? ' ('.$unit->KyHieu.')' : '' }}</option>@endforeach</select></div>
                                <div class="col-md-6"><label class="form-label">Số lượng</label><input type="number" step="1" min="1" class="form-control booking-quantity" name="items[{{ $index }}][SoLuong]" value="{{ $quantityValue }}"></div>
                                <div class="col-md-6"><label class="form-label">Khối lượng (kg)</label><input type="number" step="0.01" min="0.01" class="form-control booking-weight" name="items[{{ $index }}][KhoiLuong]" value="{{ $item['KhoiLuong'] ?? '' }}"></div>
                                @if($inspectionMode)
                                    <div class="col-12"><label class="form-label">Tình trạng trước khi giặt <span class="text-danger">*</span></label><textarea class="form-control" name="items[{{ $index }}][TinhTrangTruocKhiGiat]" rows="2" maxlength="320" placeholder="VD: ố màu, rách, bung chỉ..." required>{{ $item['TinhTrangTruocKhiGiat'] ?? '' }}</textarea></div>
                                @endif
                                <div class="col-12"><label class="form-label">Ghi chú dòng</label><input type="text" class="form-control" name="items[{{ $index }}][GhiChu]" value="{{ $item['GhiChu'] ?? '' }}" maxlength="{{ $inspectionMode ? 160 : 500 }}"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
                @if(! $inspectionMode)
                <div class="col-md-6"><label class="form-label">Trạng thái</label><x-admin.status-select name="status" id="booking-status" :options="\App\Enums\BookingStatus::options()" :selected="old('status', $booking->TrangThai)" class="form-select @error('status') is-invalid @enderror" />@error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                @else
                    <div class="col-12">
                        <input type="hidden" name="use_points" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="booking-use-points" name="use_points" value="1" @checked((bool) old('use_points', (int) $booking->DiemSuDung > 0))>
                            <label class="form-check-label" for="booking-use-points">Dùng điểm tích lũy (tối đa theo tổng tiền thực tế)</label>
                        </div>
                        <div class="form-text">Khách đang có {{ number_format($customer?->points() ?? 0) }} điểm. Điểm chỉ được trừ khi tạo đơn thành công.</div>
                        <div class="alert alert-info mt-3 mb-0">Kiểm tra trực tiếp loại đồ, dịch vụ, số lượng/kg và tình trạng trước khi giặt. Khi xác nhận, đơn hàng được tạo ở trạng thái Đã tiếp nhận.</div>
                    </div>
                @endif
                <div class="col-12"><label class="form-label">Ghi chú</label><textarea class="form-control @error('notes') is-invalid @enderror" name="notes" rows="3" maxlength="500">{{ old('notes', $booking->GhiChu) }}</textarea>@error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4"><button type="reset" class="btn btn-outline-secondary">Làm mới</button><button type="submit" class="btn btn-primary">{{ $inspectionMode ? 'Xác nhận & tạo đơn' : 'Cập nhật' }}</button></div>
        </form>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const method = document.getElementById('booking-method');
        const inspectionMode = @json($inspectionMode);
        const returnMethod = document.getElementById('booking-return-method');
        const returnAddress = document.getElementById('booking-return-address');
        const address = document.querySelector('[name="address"]');
        const status = document.getElementById('booking-status');
        const staff = document.getElementById('booking-staff-id');
        const staffRequiredIndicator = document.querySelector('[data-staff-required-indicator]');
        const form = document.querySelector('form');
        const itemsContainer = document.getElementById('booking-items');
        const addItemButton = document.getElementById('add-booking-item');
        const pricingUnitOptions = @json($pricingUnitOptions);
        const services = @json($serviceOptions);
        let nextItemIndex = {{ count($bookingItems) }};

        function updateAddressRequirement() {
            address.required = method.value === @json(\App\Enums\ReceiveMethod::Home->value);
            returnAddress.required = returnMethod.value === @json(\App\Enums\ReturnMethod::Home->value);
        }

        function updateStaffRequirement() {
            if (staff.disabled) {
                return;
            }

            const required = inspectionMode || status?.value === @json(\App\Enums\BookingStatus::Confirmed->value);
            staff.required = required;
            staffRequiredIndicator.classList.toggle('d-none', !required);
        }

        function syncServiceOptions(item, preserveSelection = true) {
            const category = item.querySelector('.booking-service-category');
            const service = item.querySelector('.booking-service');
            const emptyMessage = item.querySelector('.booking-service-empty');
            const categoryId = category.value;
            const selectedServiceId = preserveSelection ? service.value : '';
            const matchingServices = services.filter(option => String(option.categoryId) === categoryId);

            service.replaceChildren(new Option(
                categoryId ? 'Chọn dịch vụ' : 'Chọn danh mục trước',
                '',
            ));
            matchingServices.forEach(option => {
                service.add(new Option(option.name, option.id));
            });
            service.disabled = categoryId === '' || matchingServices.length === 0;
            emptyMessage.textContent = matchingServices.length === 0 && categoryId !== ''
                ? 'Danh mục này không có dịch vụ.'
                : '';
            emptyMessage.classList.toggle('d-none', emptyMessage.textContent === '');

            if (matchingServices.some(option => String(option.id) === selectedServiceId)) {
                service.value = selectedServiceId;
            }
        }

        function syncUnitOptions(item, preserveSelection = true) {
            const serviceId = item.querySelector('.booking-service').value;
            const garmentId = item.querySelector('.booking-garment').value;
            const unit = item.querySelector('.booking-unit');
            const selectedUnitId = preserveSelection ? unit.value : '';
            const matchingUnits = pricingUnitOptions.filter(option =>
                String(option.serviceId) === serviceId
                && String(option.garmentId) === garmentId
            );

            unit.replaceChildren(new Option('Chọn đơn vị', ''));
            matchingUnits.forEach(option => {
                const unitOption = new Option(option.label, option.unitId);
                unitOption.dataset.unit = option.unit;
                unit.add(unitOption);
            });

            if (matchingUnits.some(option => String(option.unitId) === selectedUnitId)) {
                unit.value = selectedUnitId;
            } else if (matchingUnits.length === 1) {
                unit.value = String(matchingUnits[0].unitId);
            } else {
                unit.value = '';
            }

            unit.disabled = matchingUnits.length === 0;
        }

        function updateQuantityFields(item) {
            const unit = item.querySelector('.booking-unit');
            const selectedUnit = unit.options[unit.selectedIndex];
            const isWeight = /^(kg|kgs|kilogram)$/.test(selectedUnit?.dataset.unit?.trim().toLowerCase() ?? '');
            const quantity = item.querySelector('.booking-quantity');
            const weight = item.querySelector('.booking-weight');
            quantity.disabled = !unit.value || isWeight;
            weight.disabled = !unit.value || !isWeight;
            quantity.required = Boolean(unit.value) && !isWeight;
            weight.required = Boolean(unit.value) && isWeight;
            if (isWeight) {
                quantity.value = '';
            } else {
                weight.value = '';
            }
        }

        method.addEventListener('change', updateAddressRequirement);
        returnMethod.addEventListener('change', updateAddressRequirement);
        status?.addEventListener('change', updateStaffRequirement);
        form.addEventListener('reset', function () {
            setTimeout(function () {
                updateAddressRequirement();
                updateStaffRequirement();
                itemsContainer.querySelectorAll('.booking-item').forEach(function (item) {
                    syncUnitOptions(item);
                    updateQuantityFields(item);
                });
            });
        });
        itemsContainer.addEventListener('change', function (event) {
            const item = event.target.closest('.booking-item');
            if (event.target.matches('.booking-service-category')) {
                syncServiceOptions(item, false);
                item.querySelector('.booking-garment').value = '';
                syncUnitOptions(item, false);
                item.querySelector('.booking-quantity').value = '';
                item.querySelector('.booking-weight').value = '';
                updateQuantityFields(item);
            } else if (event.target.matches('.booking-service, .booking-garment')) {
                syncUnitOptions(item, false);
                updateQuantityFields(item);
            } else if (event.target.matches('.booking-unit')) {
                updateQuantityFields(item);
            }
        });
        itemsContainer.addEventListener('click', function (event) {
            if (event.target.closest('.remove-booking-item')) {
                const rows = itemsContainer.querySelectorAll('.booking-item');
                if (rows.length > 1) {
                    event.target.closest('.booking-item').remove();
                } else {
                    rows[0].querySelectorAll('select, input, textarea').forEach(field => {
                        field.value = '';
                    });
                    syncServiceOptions(rows[0], false);
                    syncUnitOptions(rows[0], false);
                    updateQuantityFields(rows[0]);
                }
            }
        });
        addItemButton.addEventListener('click', function () {
            const template = itemsContainer.querySelector('.booking-item').cloneNode(true);
            template.querySelectorAll('select, input, textarea').forEach(field => {
                field.value = '';
                field.name = field.name.replace(/items\[\d+\]/, `items[${nextItemIndex}]`);
            });
            template.querySelector('.booking-service').disabled = true;
            template.querySelector('.booking-unit').disabled = true;
            itemsContainer.appendChild(template);
            syncServiceOptions(template, false);
            syncUnitOptions(template, false);
            updateQuantityFields(template);
            nextItemIndex++;
        });
        updateAddressRequirement();
        updateStaffRequirement();
        itemsContainer.querySelectorAll('.booking-item').forEach(function (item) {
            const category = item.querySelector('.booking-service-category');
            const selectedService = services.find(option =>
                String(option.id) === item.querySelector('.booking-service').value
            );
            if (category.value === '' && selectedService) {
                category.value = String(selectedService.categoryId);
            }
            syncServiceOptions(item);
            syncUnitOptions(item);
            updateQuantityFields(item);
        });
    });
</script>
@endsection
