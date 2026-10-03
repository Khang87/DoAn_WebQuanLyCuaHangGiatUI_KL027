@extends('layouts.app')

@section('title', 'Chỉnh sửa đặt lịch - Sky Laundry')
@section('page-title', 'Chỉnh sửa đặt lịch')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4"><h5 class="mb-0">Chỉnh sửa đặt lịch</h5><a href="{{ route('bookings.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Quay lại</a></div>
        <form action="{{ route('bookings.update', $booking) }}" method="POST">
            @csrf @method('PUT')
            <div class="row g-4">
                <div class="col-md-6"><label class="form-label">Khách hàng <span class="text-danger ms-1">*</span></label><select class="form-select @error('customer_id') is-invalid @enderror" name="customer_id" required><option value="">Chọn khách hàng</option>@foreach($customers as $customer)<option value="{{ $customer->KhachHangID }}" @selected(old('customer_id', $booking->KhachHangID) == $customer->KhachHangID)>{{ $customer->HoTen }}</option>@endforeach</select>@error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6">
                    <label class="form-label" for="booking-staff-id">
                        Nhân viên phụ trách
                        <span class="text-danger d-none" data-staff-required-indicator>*</span>
                    </label>
                    <select class="form-select @error('staff_id') is-invalid @enderror" id="booking-staff-id" name="staff_id">
                        <option value="">Chọn nhân viên phụ trách</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->NhanVienID }}" @selected(old('staff_id', $booking->NhanVienID ?? '') == $employee->NhanVienID)>{{ $employee->HoTen }}</option>
                        @endforeach
                    </select>
                    @error('staff_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6"><label class="form-label">Hình thức <span class="text-danger ms-1">*</span></label><select class="form-select @error('method') is-invalid @enderror" name="method" id="booking-method" required>@foreach(\App\Enums\BookingMethod::options() as $value => $label)<option value="{{ $value }}" @selected(old('method', $booking->HinhThucNhanDo) === $value)>{{ $label }}</option>@endforeach</select>@error('method')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label">Địa chỉ nhận đồ</label><input type="text" class="form-control @error('address') is-invalid @enderror" name="address" value="{{ old('address', $booking->DiaChiNhan) }}" maxlength="255" @required(old('method', $booking->HinhThucNhanDo) === \App\Enums\BookingMethod::GiaoDo->value)>@error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label">Ngày hẹn <span class="text-danger ms-1">*</span></label><input type="date" class="form-control @error('scheduled_date') is-invalid @enderror" name="scheduled_date" value="{{ old('scheduled_date', $booking->NgayHen?->format('Y-m-d')) }}" required>@error('scheduled_date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label">Giờ hẹn <span class="text-danger ms-1">*</span></label><input type="time" class="form-control @error('scheduled_time') is-invalid @enderror" name="scheduled_time" value="{{ old('scheduled_time', $booking->GioHen?->format('H:i')) }}" required>@error('scheduled_time')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                @if($errors->any())
                    <div class="col-12"><div class="alert alert-danger mb-0">@foreach($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div></div>
                @endif
                @if($booking->statusEnum() === \App\Enums\BookingStatus::Pending || $booking->donHangs->isEmpty())
                    <div class="col-md-6">
                        <label class="form-label" for="booking-points-used">Điểm tích lũy sử dụng khi tạo đơn</label>
                        <input
                            class="form-control @error('DiemSuDung') is-invalid @enderror"
                            id="booking-points-used"
                            type="number"
                            name="DiemSuDung"
                            min="0"
                            max="{{ $booking->khachHang?->points ?? 0 }}"
                            value="{{ old('DiemSuDung', 0) }}"
                        >
                        <div class="form-text">Khách đang có {{ number_format($booking->khachHang?->points ?? 0) }} điểm (tương đương {{ number_format(($booking->khachHang?->points ?? 0) * \App\Services\OrderService::POINT_VALUE) }} VNĐ)</div>
                        @error('DiemSuDung')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @endif
                <div class="col-12"><hr class="my-1"><div class="d-flex justify-content-between align-items-center"><div><h6 class="mb-1">Dịch vụ dự kiến</h6><small class="text-muted">Có thể thêm nhiều dòng. Đơn giá và thành tiền được tính lại từ bảng giá hiệu lực khi lưu.</small></div><button type="button" class="btn btn-outline-primary btn-sm" id="add-booking-item"><i class="bi bi-plus-lg me-1"></i>Thêm dòng</button></div></div>
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
                        <div class="booking-item border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-3"><strong>Dòng dịch vụ</strong><button type="button" class="btn btn-outline-danger btn-sm remove-booking-item">Xóa dòng</button></div>
                            <div class="row g-3">
                                <div class="col-md-4"><label class="form-label">Dịch vụ</label><select class="form-select" name="items[{{ $index }}][DichVuID]"><option value="">Chọn dịch vụ</option>@foreach($services as $service)<option value="{{ $service->DichVuID }}" @selected((string) ($item['DichVuID'] ?? '') === (string) $service->DichVuID)>{{ $service->TenDichVu }}</option>@endforeach</select></div>
                                <div class="col-md-4"><label class="form-label">Loại đồ giặt</label><select class="form-select" name="items[{{ $index }}][LoaiDoGiatID]"><option value="">Chọn loại đồ</option>@foreach($garments as $garment)<option value="{{ $garment->LoaiDoGiatID }}" @selected((string) ($item['LoaiDoGiatID'] ?? '') === (string) $garment->LoaiDoGiatID)>{{ $garment->TenLoaiDoGiat }}</option>@endforeach</select></div>
                                <div class="col-md-4"><label class="form-label">Đơn vị tính</label><select class="form-select booking-unit" name="items[{{ $index }}][DonViTinhID]"><option value="">Chọn đơn vị</option>@foreach($units as $unit)<option value="{{ $unit->DonViTinhID }}" data-unit="{{ $unit->KyHieu ?: $unit->TenDonViTinh }}" @selected((string) ($item['DonViTinhID'] ?? '') === (string) $unit->DonViTinhID)>{{ $unit->TenDonViTinh }}{{ $unit->KyHieu ? ' ('.$unit->KyHieu.')' : '' }}</option>@endforeach</select></div>
                                <div class="col-md-6"><label class="form-label">Số lượng</label><input type="number" step="1" min="1" class="form-control booking-quantity" name="items[{{ $index }}][SoLuong]" value="{{ $quantityValue }}"></div>
                                <div class="col-md-6"><label class="form-label">Khối lượng (kg)</label><input type="number" step="0.01" min="0.01" class="form-control booking-weight" name="items[{{ $index }}][KhoiLuong]" value="{{ $item['KhoiLuong'] ?? '' }}"></div>
                                <div class="col-12"><label class="form-label">Ghi chú dòng</label><input type="text" class="form-control" name="items[{{ $index }}][GhiChu]" value="{{ $item['GhiChu'] ?? '' }}" maxlength="500"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="col-md-6"><label class="form-label">Trạng thái</label><x-admin.status-select name="status" id="booking-status" :options="\App\Enums\BookingStatus::options()" :selected="old('status', $booking->TrangThai)" class="form-select @error('status') is-invalid @enderror" />@error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><label class="form-label">Ghi chú</label><textarea class="form-control @error('notes') is-invalid @enderror" name="notes" rows="3" maxlength="500">{{ old('notes', $booking->GhiChu) }}</textarea>@error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4"><button type="reset" class="btn btn-outline-secondary">Làm mới</button><button type="submit" class="btn btn-primary">Cập nhật</button></div>
        </form>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const method = document.getElementById('booking-method');
        const address = document.querySelector('[name="address"]');
        const status = document.getElementById('booking-status');
        const staff = document.getElementById('booking-staff-id');
        const staffRequiredIndicator = document.querySelector('[data-staff-required-indicator]');
        const form = document.querySelector('form');
        const itemsContainer = document.getElementById('booking-items');
        const addItemButton = document.getElementById('add-booking-item');
        let nextItemIndex = {{ count($bookingItems) }};

        function updateAddressRequirement() {
            address.required = method.value === @json(\App\Enums\BookingMethod::GiaoDo->value);
        }

        function updateStaffRequirement() {
            const required = status.value === @json(\App\Enums\BookingStatus::Confirmed->value);
            staff.required = required;
            staffRequiredIndicator.classList.toggle('d-none', !required);
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
        status.addEventListener('change', updateStaffRequirement);
        form.addEventListener('reset', function () {
            setTimeout(function () {
                updateAddressRequirement();
                updateStaffRequirement();
                itemsContainer.querySelectorAll('.booking-item').forEach(updateQuantityFields);
            });
        });
        itemsContainer.addEventListener('change', function (event) {
            if (event.target.matches('.booking-unit')) {
                updateQuantityFields(event.target.closest('.booking-item'));
            }
        });
        itemsContainer.addEventListener('click', function (event) {
            if (event.target.closest('.remove-booking-item')) {
                const rows = itemsContainer.querySelectorAll('.booking-item');
                if (rows.length > 1) {
                    event.target.closest('.booking-item').remove();
                } else {
                    rows[0].querySelectorAll('select, input').forEach(field => field.value = '');
                    updateQuantityFields(rows[0]);
                }
            }
        });
        addItemButton.addEventListener('click', function () {
            const template = itemsContainer.querySelector('.booking-item').cloneNode(true);
            template.querySelectorAll('select, input').forEach(field => {
                field.value = '';
                field.name = field.name.replace(/items\[\d+\]/, `items[${nextItemIndex}]`);
            });
            itemsContainer.appendChild(template);
            updateQuantityFields(template);
            nextItemIndex++;
        });
        updateAddressRequirement();
        updateStaffRequirement();
        itemsContainer.querySelectorAll('.booking-item').forEach(updateQuantityFields);
    });
</script>
@endsection
