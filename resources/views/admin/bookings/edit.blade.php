@extends('layouts.app')

@section('title', ($inspectionMode ?? false) ? 'Kiểm tra thực tế - Sky Laundry' : 'Chỉnh sửa đặt lịch - Sky Laundry')
@section('page-title', ($inspectionMode ?? false) ? 'Kiểm tra thực tế Booking' : 'Chỉnh sửa đặt lịch')

@push('styles')
<style>
    #booking-edit-form .booking-main-control {
        height: 2.5rem;
    }

    #booking-edit-form .booking-address-suggestions {
        max-height: 16rem;
        overflow-y: auto;
        z-index: 1080;
    }

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
    $intakeDocument = $booking->giaoNhanDuKiens->firstWhere('LoaiGiaoNhan', 'NHAN_DO');
    $intakeEmployee = $intakeDocument?->nhanVien;
    $legacyRequestPrefix = 'Khách chọn dịch vụ chưa rõ số lượng: ';
    $bookingNoteLines = preg_split('/\r\n|\r|\n/', (string) ($booking->GhiChu ?? ''));
    $legacyRequestedServices = collect($bookingNoteLines)
        ->filter(fn ($line) => str_starts_with($line, $legacyRequestPrefix))
        ->map(fn ($line) => rtrim(substr($line, strlen($legacyRequestPrefix)), '.'))
        ->values();
    $visibleBookingNotes = collect($bookingNoteLines)
        ->reject(fn ($line) => str_starts_with($line, $legacyRequestPrefix))
        ->implode("\n");
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
                    <input class="form-control booking-main-control bg-light text-muted" id="booking-customer-name" value="{{ $customerName ?: 'Không tìm thấy thông tin khách hàng' }}" disabled>
                </div>
                <div class="{{ $inspectionMode ? 'col-md-6' : 'col-md-4' }}">
                    <label class="form-label" for="booking-customer-phone">Số điện thoại</label>
                    <input class="form-control booking-main-control bg-light text-muted" id="booking-customer-phone" value="{{ $customerPhone ?: '—' }}" disabled>
                </div>
                <div class="{{ $inspectionMode ? 'col-md-6' : 'col-md-4' }}">
                    <label class="form-label" for="booking-customer-email">Email</label>
                    <input class="form-control booking-main-control bg-light text-muted" id="booking-customer-email" value="{{ $customerEmail ?: '—' }}" disabled>
                </div>

                @if($inspectionMode)
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="border rounded-3 p-3">
                            <h6 class="mb-3">Thông tin nhận và trả đồ từ Booking</h6>
                            <div class="row g-3">
                @endif
                <div class="col-md-6"><label class="form-label">Hình thức nhận đồ <span class="text-danger ms-1">*</span></label><select class="form-select booking-main-control @error('method') is-invalid @enderror" name="method" id="booking-method" required @disabled($inspectionMode)>@foreach(\App\Enums\ReceiveMethod::options() as $value => $label)<option value="{{ $value }}" @selected(old('method', $booking->HinhThucNhanDo) === $value)>{{ $label }}</option>@endforeach</select>@if($inspectionMode)<input type="hidden" name="method" value="{{ $booking->HinhThucNhanDo }}">@endif @error('method')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6">
                    <label class="form-label" for="booking-pickup-address">Địa chỉ nhận đồ</label>
                    <div class="position-relative">
                        <input
                            type="text"
                            id="booking-pickup-address"
                            class="form-control booking-main-control @error('address') is-invalid @enderror"
                            name="address"
                            value="{{ old('address', $booking->DiaChiNhan) }}"
                            maxlength="255"
                            @if($inspectionMode)
                                data-address-autocomplete
                                data-autocomplete-url="{{ route('bookings.address-autocomplete') }}"
                                aria-autocomplete="list"
                                aria-controls="booking-pickup-address-suggestions"
                                aria-expanded="false"
                                autocomplete="off"
                            @endif
                            @required(old('method', $booking->HinhThucNhanDo) === \App\Enums\ReceiveMethod::Home->value)
                            @disabled(old('method', $booking->HinhThucNhanDo) !== \App\Enums\ReceiveMethod::Home->value)
                            @readonly($inspectionMode)
                        >
                        @if($inspectionMode)
                            <div id="booking-pickup-address-suggestions" class="list-group booking-address-suggestions position-absolute top-100 start-0 w-100 shadow d-none" role="listbox" aria-label="Gợi ý địa chỉ nhận đồ"></div>
                            <div class="form-text" data-address-autocomplete-status role="status" aria-live="polite"></div>
                        @endif
                        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="booking-return-method">Hình thức giao <span class="text-danger">*</span></label>
                    <select class="form-select booking-main-control @error('return_method') is-invalid @enderror" name="return_method" id="booking-return-method" required @disabled($inspectionMode)>
                        <option value="">Chọn hình thức giao</option>
                        @foreach(\App\Enums\ReturnMethod::options() as $value => $label)
                            <option value="{{ $value }}" @selected(old('return_method', $booking->HinhThucTraDo) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @if($inspectionMode)
                        <input type="hidden" name="return_method" value="{{ $booking->HinhThucTraDo }}">
                    @endif
                    @error('return_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="booking-return-address">Địa chỉ trả đồ</label>
                    <div class="position-relative">
                        <input
                            type="text"
                            id="booking-return-address"
                            class="form-control booking-main-control @error('return_address') is-invalid @enderror"
                            name="return_address"
                            value="{{ old('return_address', $booking->DiaChiTra) }}"
                            maxlength="255"
                            @if($inspectionMode)
                                data-address-autocomplete
                                data-autocomplete-url="{{ route('bookings.address-autocomplete') }}"
                                aria-autocomplete="list"
                                aria-controls="booking-return-address-suggestions"
                                aria-expanded="false"
                                autocomplete="off"
                            @endif
                            @required(old('return_method', $booking->HinhThucTraDo) === \App\Enums\ReturnMethod::Home->value)
                            @disabled(old('return_method', $booking->HinhThucTraDo) !== \App\Enums\ReturnMethod::Home->value)
                            @readonly($inspectionMode)
                        >
                        @if($inspectionMode)
                            <div id="booking-return-address-suggestions" class="list-group booking-address-suggestions position-absolute top-100 start-0 w-100 shadow d-none" role="listbox" aria-label="Gợi ý địa chỉ trả đồ"></div>
                            <div class="form-text" data-address-autocomplete-status role="status" aria-live="polite"></div>
                        @endif
                        @error('return_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
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
                                    <input class="form-control booking-main-control" id="booking-promotion" value="{{ $booking->khuyenMai?->MaKhuyenMai ?: 'Không có mã giảm giá' }}" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="booking-points">Điểm đã sử dụng</label>
                                    <input class="form-control booking-main-control" id="booking-points" value="{{ number_format((int) $booking->DiemSuDung, 0, ',', '.') }} điểm ({{ number_format((float) $booking->TienGiamDoDiem, 0, ',', '.') }} VNĐ)" readonly>
                                </div>
                            </div>
                            <div class="col-12 mt-3">
                                <form action="{{ route('bookings.apply-coupon', $booking) }}" method="POST" class="d-flex gap-2">
                                    @csrf
                                    <input type="text" name="ma_khuyen_mai" class="form-control" placeholder="Nhập mã giảm giá khác" autocomplete="off">
                                    <button type="submit" class="btn btn-primary">Áp dụng</button>
                                </form>
                                @error('ma_khuyen_mai')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-text mt-2">Nhập mã giảm giá để áp dụng vào Booking này.</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="small text-muted">Phí giao nhận được giữ theo thông tin đã lưu trên Booking; kiểm kê không gọi lại dịch vụ định tuyến.</div>
                    </div>
                @endif

            {{-- Áp dụng mã giảm giá: hiển thị trên cả trang chỉnh sửa Booking thường --}}
            <div class="col-12">
                <div class="border rounded-3 p-3 mb-3">
                    <h6 class="mb-3"><i class="fas fa-ticket me-2 text-primary"></i>Áp dụng mã giảm giá</h6>
                    @if($coupons->isNotEmpty())
                        <form action="{{ route('bookings.apply-coupon', $booking) }}" method="POST">
                            @csrf
                            <div style="display: flex; gap: 8px; align-items: flex-end;">
                                <select name="ma_khuyen_mai" class="form-select" required style="flex: 1; min-width: 0; width: auto;">
                                    <option value="" selected disabled>-- Chọn mã giảm giá --</option>
                                    @foreach($coupons as $coupon)
                                        <option value="{{ $coupon->MaKhuyenMai }}" {{ old('ma_khuyen_mai') === $coupon->MaKhuyenMai || optional($booking->khuyenMai)->MaKhuyenMai === $coupon->MaKhuyenMai ? 'selected' : '' }}>
                                            {{ $coupon->MaKhuyenMai }} · {{ $coupon->discountValueLabel() }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-primary">Áp dụng</button>
                            </div>
                        </form>
                        @error('ma_khuyen_mai')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                        <div class="form-text mt-2">Chọn một mã giảm giá đang hoạt động rồi nhấn nút Áp dụng để áp dụng vào Booking này.</div>
                    @else
                        <div class="text-muted small">Hiện chưa có mã giảm giá nào đang hoạt động.</div>
                    @endif
                </div>
            </div>

                <div class="col-md-6"><label class="form-label">Ngày hẹn <span class="text-danger ms-1">*</span></label><input type="date" class="form-control booking-main-control @error('scheduled_date') is-invalid @enderror" name="scheduled_date" value="{{ old('scheduled_date', $booking->NgayHen?->format('Y-m-d')) }}" required @readonly($inspectionMode)>@error('scheduled_date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label">Giờ hẹn <span class="text-danger ms-1">*</span></label><input type="time" class="form-control booking-main-control @error('scheduled_time') is-invalid @enderror" name="scheduled_time" value="{{ old('scheduled_time', $booking->GioHen?->format('H:i')) }}" required @readonly($inspectionMode)>@error('scheduled_time')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                @if(! $inspectionMode)
                    <div class="col-md-6"><label class="form-label">Trạng thái</label><x-admin.status-select name="status" id="booking-status" :options="\App\Enums\BookingStatus::options()" :selected="old('status', $booking->TrangThai)" class="form-select booking-main-control @error('status') is-invalid @enderror" />@error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                @endif
                @if($errors->any())
                    <div class="col-12"><div class="alert alert-danger mb-0">@foreach($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div></div>
                @endif
                @if(! $inspectionMode)
                    <div class="col-12"><hr class="my-1"><div class="d-flex justify-content-between align-items-center"><div><h6 class="mb-1">Dịch vụ dự kiến</h6><small class="text-muted">Có thể thêm nhiều dòng. Đơn giá và thành tiền được tính lại từ bảng giá hiệu lực khi lưu.</small></div></div></div>
                @endif
                @php
                    $bookingItems = old('items', $booking->chiTietBookings->map(fn ($item) => $item->only(['DichVuID', 'LoaiDoGiatID', 'DonViTinhID', 'SoLuong', 'KhoiLuong', 'GhiChu', 'TinhTrangTruocKhiGiat']))->all());
                    if ($inspectionMode && old('items') === null) {
                        $existingTuples = collect($bookingItems)->mapWithKeys(fn ($item) => [implode(':', [
                            $item['DichVuID'] ?? '', $item['LoaiDoGiatID'] ?? '', $item['DonViTinhID'] ?? '',
                        ]) => true]);
                        foreach ($booking->requestedServices as $requestedService) {
                            $tuple = implode(':', [
                                $requestedService->DichVuID,
                                $requestedService->LoaiDoGiatID,
                                $requestedService->DonViTinhID,
                            ]);
                            if ($existingTuples->has($tuple)) {
                                continue;
                            }
                            $isWeightUnit = $requestedService->donViTinh?->isWeightUnit() ?? false;
                            $bookingItems[] = [
                                'DichVuID' => $requestedService->DichVuID,
                                'service_category_id' => $requestedService->dichVu?->LoaiDichVuID,
                                'LoaiDoGiatID' => $requestedService->LoaiDoGiatID,
                                'DonViTinhID' => $requestedService->DonViTinhID,
                                'SoLuong' => $isWeightUnit ? '' : '0',
                                'KhoiLuong' => $isWeightUnit ? '0' : '',
                            ];
                            $existingTuples->put($tuple, true);
                        }
                    }
                    if ($bookingItems === []) {
                        $bookingItems = [[]];
                    }
                @endphp
                @if($inspectionMode)
                    @if($booking->requestedServices->isNotEmpty() || $legacyRequestedServices->isNotEmpty())
                        <div class="col-12">
                            <div class="alert alert-info mb-2">
                                <strong>Dịch vụ khách yêu cầu · đã thêm vào bảng kiểm kê bên dưới</strong>
                                <div class="small mt-1">Các dòng chưa biết số lượng/khối lượng bắt đầu từ 0. Nhân viên kiểm tra thực tế rồi cập nhật trước khi báo giá.</div>
                                @if($booking->requestedServices->isNotEmpty())
                                    <ul class="mb-0 mt-2">
                                        @foreach($booking->requestedServices as $requestedService)
                                            <li>
                                                {{ $requestedService->dichVu?->TenDichVu ?? 'Dịch vụ không còn tồn tại' }}
                                                · {{ $requestedService->loaiDoGiat?->TenLoaiDoGiat ?? 'Loại đồ không còn tồn tại' }}
                                                (đơn vị dự kiến {{ $requestedService->donViTinh?->KyHieu ?: $requestedService->donViTinh?->TenDonViTinh ?: 'chưa rõ' }})
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <ul class="mb-0 mt-2">
                                        @foreach($legacyRequestedServices as $legacyRequest)
                                            <li>{{ $legacyRequest }} <span class="text-muted">(yêu cầu cũ lưu trong ghi chú)</span></li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    @endif
                    <div class="col-12">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <h6 class="mb-0">Mặt hàng từ Booking · kiểm tra và chỉnh nếu cần</h6>
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
                                        <th scope="col"></th>
                                    </tr>
                                </thead>
                                <tbody id="booking-items">
                                    @foreach($bookingItems as $index => $item)
                                        @php
                                            $selectedService = $services->firstWhere('DichVuID', $item['DichVuID'] ?? null);
                                            $selectedCategoryId = $item['service_category_id'] ?? $selectedService?->LoaiDichVuID;
                                            $selectedPricing = $pricingUnitOptions->first(fn (array $option): bool => (string) $option['serviceId'] === (string) ($item['DichVuID'] ?? '') && (string) $option['garmentId'] === (string) ($item['LoaiDoGiatID'] ?? '') && (string) $option['unitId'] === (string) ($item['DonViTinhID'] ?? ''));
                                            $isWeightPricing = $selectedPricing && \App\Models\BangGia::isWeightUnit($selectedPricing['unit']);
                                            $measuredAmount = $isWeightPricing ? ($item['KhoiLuong'] ?? null) : ($item['SoLuong'] ?? null);
                                            $estimatedSubtotal = $selectedPricing && is_numeric($measuredAmount) && (float) $measuredAmount > 0
                                                ? app(\App\Services\TinhTienGiatUiService::class)->tinhThanhTienChiTiet([
                                                    'DonGia' => $selectedPricing['price'],
                                                    'KyHieu' => $selectedPricing['unit'],
                                                    'SoLuong' => $isWeightPricing ? 0 : $item['SoLuong'],
                                                    'KhoiLuong' => $isWeightPricing ? $item['KhoiLuong'] : 0,
                                                ])
                                                : null;
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
                                            <td><input type="number" step="1" min="{{ $inspectionMode ? '0' : '1' }}" class="form-control booking-quantity item-quantity" name="items[{{ $index }}][SoLuong]" value="{{ $quantityValue }}" aria-label="Số lượng"></td>
                                            <td><input type="number" step="0.01" min="{{ $inspectionMode ? '0' : '0.01' }}" class="form-control booking-weight item-weight" name="items[{{ $index }}][KhoiLuong]" value="{{ $item['KhoiLuong'] ?? '' }}" aria-label="Khối lượng (kg)"></td>
                                            <td><input type="text" class="form-control booking-item-price item-price text-end" value="{{ $selectedPricing ? number_format((float) $selectedPricing['price'], 0, ',', '.').' VNĐ' : '—' }}" readonly aria-label="Đơn giá"></td>
                                            <td><input type="text" class="form-control booking-item-subtotal item-subtotal text-end" value="{{ $estimatedSubtotal !== null ? number_format($estimatedSubtotal, 0, ',', '.').' VNĐ' : ($selectedPricing ? 'Chờ kiểm tra' : '—') }}" readonly aria-label="Thành tiền"></td>
                                            <td><textarea class="form-control" name="items[{{ $index }}][TinhTrangTruocKhiGiat]" rows="2" maxlength="320" placeholder="VD: ố màu, rách, bung chỉ..." required>{{ $item['TinhTrangTruocKhiGiat'] ?? '' }}</textarea></td>
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
                @if($inspectionMode)
                    <div class="col-12">
                        <div class="alert alert-info mb-0">Thông tin lịch hẹn ban đầu được giữ nguyên. Đối chiếu và cập nhật số lượng, khối lượng, loại đồ, dịch vụ và tình trạng theo thực tế; giá được tính lại bằng bảng giá hiện hành. Sau khi báo tổng tiền cho khách, chỉ tiếp tục khi khách đồng ý.</div>
                    </div>
                @endif
                <div class="col-12">
                    <label class="form-label">{{ $inspectionMode ? 'Ghi chú từ khách hàng' : 'Ghi chú' }}</label>
                    <textarea class="form-control @error('notes') is-invalid @enderror" @unless($inspectionMode) name="notes" @endunless rows="3" maxlength="500" @readonly($inspectionMode)>{{ $inspectionMode ? $visibleBookingNotes : old('notes', $booking->GhiChu) }}</textarea>
                    @if($inspectionMode)<input type="hidden" name="notes" value="{{ old('notes', $booking->GhiChu) }}">@endif
                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            @if($inspectionMode)
                <div
                    class="card border-0 bg-light mt-3"
                    data-booking-estimate="{{ route('bookings.estimate', $booking) }}"
                    data-pricing-options="{{ $pricingUnitOptions->toJson(JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
                    data-service-options="{{ $serviceOptions->toJson(JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
                    data-garment-options="{{ $garmentOptions->toJson(JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
                    data-minimum-weight="{{ config('giatui.khoi_luong_toi_thieu', 3.0) }}"
                >
                    <div class="card-body">
                        <h6 class="mb-0">Tổng kết đơn hàng</h6>
                        <div class="table-responsive mt-3" data-estimate-items></div>
                        <div data-estimate-warning class="small text-danger mt-2" role="status" aria-live="polite"></div>
                        <div class="mt-3" data-estimate-details></div>
                        <div class="form-text mt-2">Giá hiển thị là ước tính theo bảng giá hiện hành; giá, khuyến mãi, điểm và phí được máy chủ kiểm tra lại khi xác nhận.</div>
                        <div class="border-top mt-3 pt-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <span class="fw-bold">Tổng thanh toán</span>
                            <span class="fw-bold fs-5 text-primary" data-estimate-total>Đang tính…</span>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="form-check border rounded-3 p-3">
                        <input class="form-check-input ms-0 me-2" type="checkbox" id="customer_accepted" name="customer_accepted" value="1" required @checked(old('customer_accepted'))>
                        <label class="form-check-label fw-semibold" for="customer_accepted">
                            Tôi xác nhận đã báo kết quả kiểm kê và tổng tiền thực tế trực tiếp cho khách hàng, và khách đã đồng ý bằng lời.
                        </label>
                        <div class="form-text">Đây là ghi nhận của nhân viên, không phải chữ ký hay bằng chứng xác nhận độc lập từ khách.</div>
                        @error('customer_accepted')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                    </div>
                </div>
            @endif
            <div class="d-flex flex-wrap justify-content-between gap-2 mt-4">
            @if($inspectionMode)
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#booking-refusal-modal">
                    Khách từ chối — Hủy Booking
                </button>
            @endif
            <button type="submit" class="btn {{ $inspectionMode ? 'btn-success' : 'btn-primary' }} ms-auto"><i class="bi bi-check-lg me-1"></i>{{ $inspectionMode ? 'Xác nhận nhận đồ và tạo đơn' : 'Cập nhật' }}</button>
            </div>
            @if($inspectionMode)
            <div class="modal fade" id="booking-refusal-modal" tabindex="-1" aria-labelledby="booking-refusal-title" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="booking-refusal-title">Xác nhận khách từ chối báo giá</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                        </div>
                        <div class="modal-body">
                            <p>Booking sẽ bị hủy, không tạo đơn hàng, không ghi nhận đã nhận đồ và không thu phí lấy đồ. Dữ liệu kiểm kê hiện tại và lý do từ chối sẽ được lưu để tra cứu.</p>
                            <label class="form-label" for="rejection_reason">Lý do khách từ chối <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('rejection_reason') is-invalid @enderror" id="rejection_reason" name="rejection_reason" rows="3" maxlength="500" disabled>{{ old('rejection_reason') }}</textarea>
                            @error('rejection_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Quay lại kiểm kê</button>
                            <button type="submit" class="btn btn-danger" formaction="{{ route('bookings.refuse-quote', $booking) }}" data-refusal-submit>Hủy Booking và lưu lý do</button>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </form>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const method = document.getElementById('booking-method');
        const inspectionMode = @json($inspectionMode);
        const form = document.getElementById('booking-edit-form');
        const customerAcceptance = document.getElementById('customer_accepted');
        const refusalModal = document.getElementById('booking-refusal-modal');
        const refusalReason = document.getElementById('rejection_reason');
        const returnMethod = document.getElementById('booking-return-method');
        const returnAddress = document.getElementById('booking-return-address');
        const address = document.querySelector('[name="address"]');
        const itemsContainer = document.getElementById('booking-items');
        const addItemButton = document.getElementById('add-booking-item');
        const pricingUnitOptions = @json($pricingUnitOptions);
        const services = @json($serviceOptions);
        const garments = @json($garmentOptions);
        let nextItemIndex = {{ count($bookingItems) }};

        if (inspectionMode && refusalModal && refusalReason) {
            refusalModal.addEventListener('show.bs.modal', function () {
                refusalReason.disabled = false;
                refusalReason.required = true;
                customerAcceptance.required = false;
            });
            refusalModal.addEventListener('hidden.bs.modal', function () {
                refusalReason.disabled = true;
                refusalReason.required = false;
                customerAcceptance.required = true;
            });
            form.addEventListener('input', function (event) {
                if (event.target.name?.startsWith('items[')) {
                    customerAcceptance.checked = false;
                }
            });
            form.addEventListener('change', function (event) {
                if (event.target.name?.startsWith('items[')) {
                    customerAcceptance.checked = false;
                }
            });
            form.querySelector('[data-refusal-submit]').addEventListener('click', function () {
                refusalReason.disabled = false;
                refusalReason.required = true;
                customerAcceptance.required = false;
            });
            @if($errors->has('rejection_reason'))
                bootstrap.Modal.getOrCreateInstance(refusalModal).show();
                refusalReason.disabled = false;
                refusalReason.required = true;
                customerAcceptance.required = false;
            @endif
        }

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
