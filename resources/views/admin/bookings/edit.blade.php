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
        @include('admin.bookings.mobile-summary')
        <div data-booking-row-status class="form-text mb-2" role="status"></div>
        <form id="booking-edit-form" action="{{ route($inspectionMode ? 'bookings.confirm' : 'bookings.update', $booking) }}" method="POST">
            @csrf @if(! $inspectionMode) @method('PUT') @endif
            <div class="row g-4">
                <input type="hidden" name="customer_id" value="{{ $booking->KhachHangID }}">
                @if($inspectionMode)
                    <div class="col-12">
                        <div class="border rounded-3 p-3">
                            <h6 class="mb-3">Thông tin khách hàng và tiếp nhận</h6>
                            <div class="row g-3">
                @endif
                <div class="{{ $inspectionMode ? 'col-md-6' : 'col-md-4' }}">
                    <label class="form-label" for="booking-customer-name">Khách hàng</label>
                    <input class="form-control bg-light text-muted" id="booking-customer-name" value="{{ $customerName ?: 'Không tìm thấy thông tin khách hàng' }}" disabled>
                </div>
                <div class="{{ $inspectionMode ? 'col-md-6' : 'col-md-4' }}">
                    <label class="form-label" for="booking-customer-phone">Số điện thoại</label>
                    <input class="form-control bg-light text-muted" id="booking-customer-phone" value="{{ $customerPhone ?: '—' }}" disabled>
                </div>
                <div class="{{ $inspectionMode ? 'col-md-6' : 'col-md-4' }}">
                    <label class="form-label" for="booking-customer-email">Email</label>
                    <input class="form-control bg-light text-muted" id="booking-customer-email" value="{{ $customerEmail ?: '—' }}" disabled>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="booking-staff-name">
                        {{ $inspectionMode ? 'Nhân viên tiếp nhận Booking' : 'Nhân viên phụ trách hiện tại' }}
                    </label>
                    <input
                        type="text"
                        class="form-control bg-light text-muted"
                        id="booking-staff-name"
                        value="{{ $inspectionMode
                            ? ($authenticatedEmployee?->HoTen ?? 'Chưa có hồ sơ nhân viên đang hoạt động')
                            : ($booking->nhanVien?->HoTen ?? 'Chưa phân công') }}"
                        readonly
                    >
                    @if($inspectionMode)
                        <div class="form-text">Booking được tiếp nhận bởi nhân viên thuộc tài khoản đang đăng nhập.</div>
                    @else
                        <div class="form-text">Thông tin này chỉ để xem; không thể đổi nhân viên phụ trách tại đây.</div>
                    @endif
                </div>
                @if($inspectionMode)
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="border rounded-3 p-3">
                            <h6 class="mb-3">Thông tin nhận và trả đồ</h6>
                            <div class="row g-3">
                @endif
                <div class="col-md-6"><label class="form-label">Hình thức nhận đồ <span class="text-danger ms-1">*</span></label><select class="form-select @error('method') is-invalid @enderror" name="method" id="booking-method" required>@foreach(\App\Enums\ReceiveMethod::options() as $value => $label)<option value="{{ $value }}" @selected(old('method', $booking->HinhThucNhanDo) === $value)>{{ $label }}</option>@endforeach</select>@error('method')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label">Địa chỉ nhận đồ</label><input type="text" class="form-control @error('address') is-invalid @enderror" name="address" value="{{ old('address', $booking->DiaChiNhan) }}" maxlength="255" @required(old('method', $booking->HinhThucNhanDo) === \App\Enums\ReceiveMethod::Home->value) @disabled(old('method', $booking->HinhThucNhanDo) !== \App\Enums\ReceiveMethod::Home->value)>@error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
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
                    <input type="text" id="booking-return-address" class="form-control @error('return_address') is-invalid @enderror" name="return_address" value="{{ old('return_address', $booking->DiaChiTra) }}" maxlength="255" @required(old('return_method', $booking->HinhThucTraDo) === \App\Enums\ReturnMethod::Home->value) @disabled(old('return_method', $booking->HinhThucTraDo) !== \App\Enums\ReturnMethod::Home->value)>
                    @error('return_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                @if($inspectionMode)
                            </div>
                        </div>
                    </div>
                @endif
                @if($inspectionMode)
                    <div class="col-12">
                        <div class="border rounded-3 p-3">
                            <h6 class="mb-2">Ưu đãi từ Booking</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="booking-promotion">Mã giảm giá</label>
                                    <input class="form-control" id="booking-promotion" value="{{ $booking->khuyenMai?->MaKhuyenMai ?: 'Không có mã giảm giá' }}" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="booking-points">Điểm đã sử dụng</label>
                                    <input class="form-control" id="booking-points" value="{{ number_format((int) $booking->DiemSuDung, 0, ',', '.') }} điểm ({{ number_format((float) $booking->TienGiamDoDiem, 0, ',', '.') }} VNĐ)" readonly>
                                </div>
                            </div>
                            <div class="form-text mt-2">Ưu đãi chỉ được kế thừa từ Booking; không thể thêm, đổi hoặc bỏ tại màn hình kiểm tra.</div>
                        </div>
                    </div>
                    @if(config('giatui.booking_two_step'))
                        <div class="col-12 form-check ms-3">
                            <input class="form-check-input" type="checkbox" id="quality_difference" name="quality_difference" value="1" @checked(old('quality_difference'))>
                            <label class="form-check-label" for="quality_difference">Có chênh lệch về tình trạng/chất lượng so với khách khai báo</label>
                        </div>
                    @endif
                    <div class="col-12">
                        <button type="button" class="btn btn-outline-primary" id="booking-quote-delivery">
                            <i class="bi bi-geo-alt me-1"></i>Tính lại phí giao nhận
                        </button>
                        <div class="small mt-2" id="booking-delivery-quote-status" role="status" aria-live="polite">
                            Phí đang dùng là phí đã lưu từ Booking. Nhấn nút sau khi thay đổi địa chỉ hoặc phương thức nếu muốn tính lại.
                        </div>
                        <div class="row g-2 small mt-1" id="booking-delivery-quote-result" hidden>
                            <div class="col-md-6">Phí nhận đồ: <strong data-pickup-fee></strong></div>
                            <div class="col-md-6">Phí giao đồ: <strong data-return-fee></strong></div>
                        </div>
                        <input type="hidden" name="recalculate_delivery_fee" value="0">
                    </div>
                @endif
                <div class="col-md-6"><label class="form-label">Ngày hẹn <span class="text-danger ms-1">*</span></label><input type="date" class="form-control @error('scheduled_date') is-invalid @enderror" name="scheduled_date" value="{{ old('scheduled_date', $booking->NgayHen?->format('Y-m-d')) }}" required>@error('scheduled_date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label">Giờ hẹn <span class="text-danger ms-1">*</span></label><input type="time" class="form-control @error('scheduled_time') is-invalid @enderror" name="scheduled_time" value="{{ old('scheduled_time', $booking->GioHen?->format('H:i')) }}" required>@error('scheduled_time')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                @if($errors->any())
                    <div class="col-12"><div class="alert alert-danger mb-0">@foreach($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div></div>
                @endif
                @if(! $inspectionMode)
                    <div class="col-12"><hr class="my-1"><div class="d-flex justify-content-between align-items-center"><div><h6 class="mb-1">Dịch vụ dự kiến</h6><small class="text-muted">Có thể thêm nhiều dòng. Đơn giá và thành tiền được tính lại từ bảng giá hiệu lực khi lưu.</small></div></div></div>
                @endif
                @php
                    $bookingItems = old('items', $booking->chiTietBookings->map(fn ($item) => $item->only(['DichVuID', 'LoaiDoGiatID', 'DonViTinhID', 'SoLuong', 'KhoiLuong', 'GhiChu', 'TinhTrangTruocKhiGiat']))->all());
                    if ($bookingItems === []) {
                        $bookingItems = [[]];
                    }
                @endphp
                @if($inspectionMode)
                    <div class="col-12">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <h6 class="mb-0">Mặt hàng và dịch vụ thực tế</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="add-booking-item">
                                <i class="bi bi-plus-lg me-1"></i>Thêm mặt hàng
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-2" id="booking-items-table">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">STT</th>
                                        <th scope="col">Danh mục dịch vụ</th>
                                        <th scope="col">Dịch vụ</th>
                                        <th scope="col">Loại đồ giặt</th>
                                        <th scope="col">ĐVT</th>
                                        <th scope="col">Số lượng</th>
                                        <th scope="col">Khối lượng (kg)</th>
                                        <th scope="col">Đơn giá</th>
                                        <th scope="col">Thành tiền</th>
                                        <th scope="col">Tình trạng trước khi giặt</th>
                                        <th scope="col">Ghi chú</th>
                                        <th scope="col"></th>
                                    </tr>
                                </thead>
                                <tbody id="booking-items">
                                    @foreach($bookingItems as $index => $item)
                                        @php
                                            $selectedService = $services->firstWhere('DichVuID', $item['DichVuID'] ?? null);
                                            $selectedCategoryId = $item['service_category_id'] ?? $selectedService?->LoaiDichVuID;
                                            $quantityValue = $item['SoLuong'] ?? '';
                                            if (is_numeric($quantityValue) && floor((float) $quantityValue) === (float) $quantityValue) {
                                                $quantityValue = (string) (int) $quantityValue;
                                            }
                                        @endphp
                                        <tr class="booking-item">
                                            <td data-row-number>{{ $loop->iteration }}</td>
                                            <td>
                                                <select class="form-select booking-service-category" name="items[{{ $index }}][service_category_id]" aria-label="Danh mục dịch vụ">
                                                    <option value="">Danh mục</option>
                                                    @foreach($serviceCategories as $category)
                                                        <option value="{{ $category->LoaiDichVuID }}" @selected((string) $selectedCategoryId === (string) $category->LoaiDichVuID)>{{ $category->TenLoaiDichVu }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <select class="form-select booking-service item-service" name="items[{{ $index }}][DichVuID]" aria-label="Dịch vụ">
                                                    <option value="">Chọn dịch vụ</option>
                                                    @foreach($services as $service)
                                                        <option value="{{ $service->DichVuID }}" @selected((string) ($item['DichVuID'] ?? '') === (string) $service->DichVuID)>{{ $service->TenDichVu }}</option>
                                                    @endforeach
                                                </select>
                                                <div class="form-text booking-service-empty d-none" aria-live="polite"></div>
                                            </td>
                                            <td>
                                                <select class="form-select booking-garment item-garment" name="items[{{ $index }}][LoaiDoGiatID]" aria-label="Loại đồ giặt">
                                                    <option value="">Chọn loại đồ</option>
                                                    @foreach($garments as $garment)
                                                        @if($pricingUnitOptions->contains(fn (array $option): bool => (string) $option['serviceId'] === (string) ($item['DichVuID'] ?? '') && (string) $option['garmentId'] === (string) $garment->LoaiDoGiatID))
                                                            <option value="{{ $garment->LoaiDoGiatID }}" @selected((string) ($item['LoaiDoGiatID'] ?? '') === (string) $garment->LoaiDoGiatID)>{{ $garment->TenLoaiDoGiat }}</option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <select class="form-select booking-unit item-unit-value" name="items[{{ $index }}][DonViTinhID]" aria-label="Đơn vị tính">
                                                    <option value="">Chọn đơn vị</option>
                                                    @foreach($units as $unit)
                                                        <option value="{{ $unit->DonViTinhID }}" data-unit="{{ $unit->KyHieu ?: $unit->TenDonViTinh }}" @selected((string) ($item['DonViTinhID'] ?? '') === (string) $unit->DonViTinhID)>{{ $unit->KyHieu ?: $unit->TenDonViTinh }}</option>
                                                    @endforeach
                                                </select>
                                                <div class="form-text text-danger booking-price-warning" data-price-warning role="status"></div>
                                            </td>
                                            <td><input type="number" step="1" min="1" class="form-control booking-quantity item-quantity" name="items[{{ $index }}][SoLuong]" value="{{ $quantityValue }}" aria-label="Số lượng"></td>
                                            <td><input type="number" step="0.01" min="0.01" class="form-control booking-weight item-weight" name="items[{{ $index }}][KhoiLuong]" value="{{ $item['KhoiLuong'] ?? '' }}" aria-label="Khối lượng (kg)"></td>
                                            <td><input type="text" class="form-control booking-item-price item-price text-end" value="—" readonly aria-label="Đơn giá"></td>
                                            <td><input type="text" class="form-control booking-item-subtotal item-subtotal text-end" value="—" readonly aria-label="Thành tiền"></td>
                                            <td><textarea class="form-control" name="items[{{ $index }}][TinhTrangTruocKhiGiat]" rows="2" maxlength="320" placeholder="VD: ố màu, rách, bung chỉ..." required>{{ $item['TinhTrangTruocKhiGiat'] ?? '' }}</textarea></td>
                                            <td><input type="text" class="form-control" name="items[{{ $index }}][GhiChu]" value="{{ $item['GhiChu'] ?? '' }}" maxlength="160" aria-label="Ghi chú dòng"></td>
                                            <td><button type="button" class="btn btn-sm btn-outline-danger remove-booking-item" aria-label="Xóa mặt hàng"><i class="bi bi-trash"></i></button></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="form-text">Đơn giá và thành tiền là ước tính theo bảng giá hiệu lực hiện tại; máy chủ sẽ kiểm tra lại khi xác nhận.</div>
                    </div>
                @else
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
                                    <div class="col-md-3">
                                        <label class="form-label">Loại đồ giặt</label>
                                        <select class="form-select booking-garment" name="items[{{ $index }}][LoaiDoGiatID]">
                                            <option value="">{{ ($item['DichVuID'] ?? '') ? 'Chọn loại đồ' : 'Chọn dịch vụ trước' }}</option>
                                            @foreach($garments as $garment)
                                                @if($pricingUnitOptions->contains(fn (array $option): bool => (string) $option['serviceId'] === (string) ($item['DichVuID'] ?? '') && (string) $option['garmentId'] === (string) $garment->LoaiDoGiatID))
                                                    <option value="{{ $garment->LoaiDoGiatID }}" @selected((string) ($item['LoaiDoGiatID'] ?? '') === (string) $garment->LoaiDoGiatID)>{{ $garment->TenLoaiDoGiat }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3"><label class="form-label">Đơn vị tính</label><select class="form-select booking-unit" name="items[{{ $index }}][DonViTinhID]"><option value="">Chọn đơn vị</option>@foreach($units as $unit)<option value="{{ $unit->DonViTinhID }}" data-unit="{{ $unit->KyHieu ?: $unit->TenDonViTinh }}" @selected((string) ($item['DonViTinhID'] ?? '') === (string) $unit->DonViTinhID)>{{ $unit->TenDonViTinh }}{{ $unit->KyHieu ? ' ('.$unit->KyHieu.')' : '' }}</option>@endforeach</select></div>
                                    <div class="col-md-6"><label class="form-label">Số lượng</label><input type="number" step="1" min="1" class="form-control booking-quantity" name="items[{{ $index }}][SoLuong]" value="{{ $quantityValue }}"></div>
                                    <div class="col-md-6"><label class="form-label">Khối lượng (kg)</label><input type="number" step="0.01" min="0.01" class="form-control booking-weight" name="items[{{ $index }}][KhoiLuong]" value="{{ $item['KhoiLuong'] ?? '' }}"></div>
                                    <div class="col-12"><label class="form-label">Ghi chú dòng</label><input type="text" class="form-control" name="items[{{ $index }}][GhiChu]" value="{{ $item['GhiChu'] ?? '' }}" maxlength="500"></div>
                                </div>
                            </div>
                        @endforeach
                        <button type="button" class="btn btn-outline-primary btn-sm" id="add-booking-item"><i class="bi bi-plus-lg me-1"></i>Thêm dòng dịch vụ</button>
                    </div>
                @endif
                @if(! $inspectionMode)
                <div class="col-md-6"><label class="form-label">Trạng thái</label><x-admin.status-select name="status" id="booking-status" :options="\App\Enums\BookingStatus::options()" :selected="old('status', $booking->TrangThai)" class="form-select @error('status') is-invalid @enderror" />@error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                @else
                    <div class="col-12">
                        <div class="alert alert-info mb-0">Kiểm tra trực tiếp loại đồ, dịch vụ, số lượng/kg và tình trạng trước khi giặt. Mã giảm giá và điểm sử dụng lấy từ Booking; khi xác nhận, đơn hàng được tạo ở trạng thái Đã tiếp nhận.</div>
                    </div>
                @endif
                <div class="col-12"><label class="form-label">Ghi chú</label><textarea class="form-control @error('notes') is-invalid @enderror" name="notes" rows="3" maxlength="500">{{ old('notes', $booking->GhiChu) }}</textarea>@error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            </div>
            @if($inspectionMode)
                <div
                    class="card border-0 bg-light mt-3"
                    data-booking-estimate="{{ route('bookings.estimate', $booking) }}"
                    data-delivery-quote="{{ route('bookings.delivery-fee-quote', $booking) }}"
                    data-pricing-options="{{ $pricingUnitOptions->toJson(JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
                    data-service-options="{{ $serviceOptions->toJson(JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
                    data-garment-options="{{ $garmentOptions->toJson(JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
                    data-minimum-weight="{{ config('giatui.khoi_luong_toi_thieu', 3.0) }}"
                >
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <h6 class="mb-0">Tổng kết đơn hàng</h6>
                            <div class="fw-bold fs-5 text-primary">Tổng thanh toán: <span data-estimate-total>Đang tính…</span></div>
                        </div>
                        <div data-estimate-details></div>
                        <div class="table-responsive mt-3" data-estimate-items></div>
                        <div data-estimate-warning class="small text-danger mt-2" role="status" aria-live="polite"></div>
                        <div class="form-text mt-2">Giá hiển thị là ước tính theo bảng giá hiện hành; giá, khuyến mãi, điểm và phí được máy chủ kiểm tra lại khi xác nhận.</div>
                    </div>
                </div>
            @endif
            <div class="d-flex justify-content-end gap-2 mt-4"><button type="reset" class="btn btn-outline-secondary">Làm mới</button><button type="submit" class="btn btn-primary">{{ $inspectionMode ? (config('giatui.booking_two_step') ? 'Lưu kiểm kê / tạo đơn' : 'Xác nhận & tạo đơn') : 'Cập nhật' }}</button></div>
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
        const form = document.getElementById('booking-edit-form');
        const itemsContainer = document.getElementById('booking-items');
        const addItemButton = document.getElementById('add-booking-item');
        const pricingUnitOptions = @json($pricingUnitOptions);
        const services = @json($serviceOptions);
        const garments = @json($garmentOptions);
        let nextItemIndex = {{ count($bookingItems) }};

        function updateAddressRequirement() {
            address.required = method.value === @json(\App\Enums\ReceiveMethod::Home->value);
            returnAddress.required = returnMethod.value === @json(\App\Enums\ReturnMethod::Home->value);
            for (const field of [address, returnAddress]) {
                field.disabled = !field.required;
                field.classList.toggle('bg-light', field.disabled);
                field.classList.toggle('text-muted', field.disabled);
            }
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

        function syncGarmentOptions(item, preserveSelection = true) {
            const serviceId = item.querySelector('.booking-service').value;
            const garment = item.querySelector('.booking-garment');
            const selectedGarmentId = preserveSelection ? garment.value : '';
            const matchingGarments = garments.filter(option => pricingUnitOptions.some(pricing =>
                String(pricing.serviceId) === serviceId
                && String(pricing.garmentId) === String(option.id)
            ));

            garment.replaceChildren(new Option(
                serviceId
                    ? (matchingGarments.length > 0 ? 'Chọn loại đồ' : 'Không có loại đồ có bảng giá hiệu lực')
                    : 'Chọn dịch vụ trước',
                '',
            ));
            matchingGarments.forEach(option => {
                garment.add(new Option(option.name, option.id));
            });
            garment.disabled = serviceId === '' || matchingGarments.length === 0;

            if (matchingGarments.some(option => String(option.id) === selectedGarmentId)) {
                garment.value = selectedGarmentId;
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
            let warning = item.querySelector('.booking-price-warning');
            if (!warning) {
                warning = document.createElement('div');
                warning.className = 'form-text text-danger booking-price-warning';
                warning.setAttribute('role', 'status');
                unit.parentElement.append(warning);
            }
            warning.textContent = serviceId && garmentId && matchingUnits.length === 0
                ? 'Chưa thiết lập bảng giá hiệu lực cho dịch vụ và loại đồ này. Chọn tổ hợp khác hoặc bổ sung bảng giá trước khi lưu.' : '';
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
        form.addEventListener('reset', function () {
            setTimeout(function () {
                updateAddressRequirement();
                itemsContainer.querySelectorAll('.booking-item').forEach(function (item) {
                    syncServiceOptions(item);
                    syncGarmentOptions(item);
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
                syncGarmentOptions(item, false);
                syncUnitOptions(item, false);
                item.querySelector('.booking-quantity').value = '';
                item.querySelector('.booking-weight').value = '';
                updateQuantityFields(item);
            } else if (event.target.matches('.booking-service')) {
                syncGarmentOptions(item);
                syncUnitOptions(item, false);
                updateQuantityFields(item);
            } else if (event.target.matches('.booking-garment')) {
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
                    syncGarmentOptions(rows[0], false);
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
            syncGarmentOptions(template, false);
            syncUnitOptions(template, false);
            updateQuantityFields(template);
            nextItemIndex++;
            document.querySelector('[data-booking-row-status]').textContent = 'Đã thêm dòng mới. Hãy chọn dịch vụ và loại đồ.';
            template.querySelector('.booking-service-category').focus();
        });
        form.addEventListener('reset', () => setTimeout(updateAddressRequirement, 0));
        updateAddressRequirement();
        itemsContainer.querySelectorAll('.booking-item').forEach(function (item) {
            const category = item.querySelector('.booking-service-category');
            const selectedService = services.find(option =>
                String(option.id) === item.querySelector('.booking-service').value
            );
            if (category.value === '' && selectedService) {
                category.value = String(selectedService.categoryId);
            }
            syncServiceOptions(item);
            syncGarmentOptions(item);
            syncUnitOptions(item);
            updateQuantityFields(item);
        });
    });
</script>
@endsection

@push('scripts')
    @if($inspectionMode)
        <script src="{{ asset('assets/js/order-item-pricing.js') }}"></script>
        @vite('resources/js/booking-estimate.js')
    @endif
@endpush
