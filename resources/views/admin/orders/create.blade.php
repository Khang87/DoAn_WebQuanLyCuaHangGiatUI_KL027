@extends('layouts.app')

@section('title', 'Tạo đơn hàng mới - Sky Laundry')
@section('page-title', 'Tạo đơn hàng mới')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Thông tin đơn hàng</h5>
            <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Quay lại</a>
        </div>
        @php
            $garmentOptions = $garments;
            $employeeOptions = $employees;
            $priceOptions = $pricings;
            $minimumWeight = (float) config('giatui.khoi_luong_toi_thieu', 3.0);
            $items = old('items', [['DichVuID' => '', 'LoaiDoGiatID' => '', 'SoLuong' => 1, 'KhoiLuong' => 0, 'DonGia' => 0]]);
            $pricesJson = $priceOptions->map(fn ($pricing) => [
                'service_id' => $pricing->DichVuID,
                'garment_id' => $pricing->LoaiDoGiatID,
                'price' => $pricing->DonGia,
                'unit' => $pricing->unit,
                'unit_id' => $pricing->DonViTinhID,
            ])->values()->toJson();
            $customerPointsJson = $customers->mapWithKeys(fn ($customer) => [
                $customer->KhachHangID => (int) ($customer->diemTichLuy?->DiemHienTai ?? 0),
            ])->toJson();

            $units = collect($units);
            $unitLabels = $units->mapWithKeys(fn ($unit) => [
                $unit->DonViTinhID => $unit->KyHieu ?: $unit->TenDonViTinh,
            ])->all();
        @endphp
        <form action="{{ route('orders.store') }}" method="POST" id="orderForm">
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label" for="code">Mã đơn hàng</label>
                    <input type="text" class="form-control" id="code" value="{{ $nextOrderCode }}" readonly disabled aria-describedby="order-code-help">
                    <div class="form-text" id="order-code-help">Mã được hệ thống tự tạo khi lưu đơn hàng.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="customer_search">Khách hàng <span class="text-danger ms-1">*</span></label>
                    <div class="position-relative">
                        <input type="search" class="form-control @error('KhachHangID') is-invalid @enderror" id="customer_search" placeholder="Nhập tên khách hàng..." autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="customer_suggestions" required>
                        <div class="list-group position-absolute top-100 start-0 w-100 shadow d-none" id="customer_suggestions" role="listbox"></div>
                    </div>
                    @error('KhachHangID')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    <select class="d-none" id="customer_id" name="KhachHangID" tabindex="-1" aria-hidden="true">
                        <option value="">Chọn khách hàng</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->KhachHangID }}" data-name="{{ $customer->HoTen }}" data-phone="{{ $customer->SoDienThoai }}" @selected(old('KhachHangID') == $customer->KhachHangID)>{{ $customer->HoTen }} (ID {{ $customer->KhachHangID }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="employee_id">Nhân viên phụ trách <span class="text-danger ms-1">*</span></label>
                    <select class="form-select @error('NhanVienID') is-invalid @enderror" id="employee_id" name="NhanVienID" required>
                        <option value="">Chọn nhân viên phụ trách</option>
                        @foreach($employeeOptions as $employee)
                            <option value="{{ $employee->NhanVienID }}" @selected(old('NhanVienID') == $employee->NhanVienID)>{{ $employee->HoTen }}</option>
                        @endforeach
                    </select>
                    @error('NhanVienID')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="status">Trạng thái <span class="text-danger ms-1">*</span></label>
                    <x-admin.status-select
                        name="TrangThai"
                        id="status"
                        :options="$statusFlow ?? \App\Enums\OrderStatus::options()"
                        :selected="\App\Enums\OrderStatus::Received->value"
                        class="form-select"
                        required
                    />
                </div>
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label mb-0">Các mặt hàng trong đơn</label>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addItem"><i class="bi bi-plus-lg me-1"></i>Thêm mặt hàng</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-2" id="itemsTable">
                            <thead class="table-light"><tr><th>Danh mục dịch vụ</th><th>Dịch vụ <span class="text-danger">*</span></th><th>Loại đồ giặt <span class="text-danger">*</span></th><th>ĐVT</th><th>Số lượng</th><th>Khối lượng (kg)</th><th>Đơn giá</th><th>Thành tiền</th><th></th></tr></thead>
                            <tbody>
                                @foreach($items as $index => $item)
                                    @php
                                        $selectedServiceId = $item['DichVuID'] ?? $item['service_id'] ?? '';
                                        $availableGarmentIds = $priceOptions
                                            ->where('DichVuID', $selectedServiceId)
                                            ->pluck('LoaiDoGiatID')
                                            ->all();
                                    @endphp
                                    <tr>
                                        <td><select class="form-select item-service-category" name="items[{{ $index }}][service_category_id]"><option value="">Danh mục</option>@foreach($serviceCategories as $category)<option value="{{ $category->LoaiDichVuID }}" @selected(($item['service_category_id'] ?? '') == $category->LoaiDichVuID)>{{ $category->TenLoaiDichVu }}</option>@endforeach</select></td>
                                        <td><select class="form-select item-service" name="items[{{ $index }}][DichVuID]" required><option value="">Chọn dịch vụ</option>@foreach($services as $service)<option value="{{ $service->DichVuID }}" data-category-id="{{ $service->LoaiDichVuID }}" @selected(($item['DichVuID'] ?? $item['service_id'] ?? '') == $service->DichVuID)>{{ $service->TenDichVu }}</option>@endforeach</select></td>
                                        <td><select class="form-select item-garment" name="items[{{ $index }}][LoaiDoGiatID]" required><option value="">Chọn loại đồ</option>@foreach($garmentOptions->whereIn('LoaiDoGiatID', $availableGarmentIds) as $garment)<option value="{{ $garment->LoaiDoGiatID }}" @selected(($item['LoaiDoGiatID'] ?? $item['garment_id'] ?? '') == $garment->LoaiDoGiatID)>{{ $garment->TenLoaiDoGiat }}</option>@endforeach</select></td>
                                        <td><span class="badge text-bg-light item-unit">—</span><input type="hidden" class="item-unit-value" name="items[{{ $index }}][DonViTinhID]" value="{{ $item['DonViTinhID'] ?? '' }}"></td>
                                        <td><input type="number" class="form-control item-quantity" name="items[{{ $index }}][SoLuong]" value="{{ $item['SoLuong'] ?? $item['quantity'] ?? 1 }}" min="1" required></td>
                                        <td><input type="number" step="0.01" class="form-control item-weight" name="items[{{ $index }}][KhoiLuong]" value="{{ $item['KhoiLuong'] ?? $item['weight'] ?? 0 }}" min="0"></td>
                                        <td><input type="number" class="form-control item-price" name="items[{{ $index }}][DonGia]" value="{{ $item['DonGia'] ?? $item['price'] ?? 0 }}" min="0" step="100"></td>
                                        <td><input type="number" class="form-control item-subtotal" value="{{ $item['subtotal'] ?? 0 }}" readonly></td>
                                        <td><button type="button" class="btn btn-sm btn-outline-danger remove-item" title="Xóa mặt hàng"><i class="bi bi-trash"></i></button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="form-text">Dịch vụ có đơn vị <strong>kg</strong> sẽ tính theo khối lượng, tối thiểu {{ number_format($minimumWeight, 1) }} kg; các đơn vị khác tính theo số lượng.</div>
                </div>

                {{-- ======== Ưu đãi: Voucher + Điểm tích lũy ======== --}}
                <div class="col-12">
                    <div class="card border-primary">
                        <div class="card-body">
                            <h6 class="mb-3"><i class="fas fa-ticket me-2 text-primary"></i>Ưu đãi áp dụng</h6>
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label" for="promotion_id">Mã giảm giá (Voucher)</label>
                                    <select class="form-select @error('KhuyenMaiID') is-invalid @enderror" id="promotion_id" name="KhuyenMaiID">
                                        <option value="">Không dùng voucher</option>
                                        @foreach($promotions as $promotion)
                                            <option value="{{ $promotion->KhuyenMaiID }}"
                                                    data-code="{{ $promotion->MaKhuyenMai }}"
                                                    data-type="{{ $promotion->LoaiKhuyenMai }}"
                                                    data-value="{{ (float) $promotion->GiaTriGiam }}"
                                                    data-min="{{ (float) ($promotion->GiaTriDonToiThieu ?? 0) }}"
                                                    data-max="{{ (float) ($promotion->MucGiamToiDa ?? 0) }}"
                                                    @selected(old('KhuyenMaiID') == $promotion->KhuyenMaiID)>
                                                {{ $promotion->TenKhuyenMai }} ({{ $promotion->MaKhuyenMai }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('KhuyenMaiID')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <input type="text" class="form-control form-control-sm mt-2 @error('promotion_code') is-invalid @enderror" id="promotion_code" name="promotion_code"
                                           value="{{ old('promotion_code') }}" placeholder="Hoặc nhập mã voucher...">
                                    @error('promotion_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    <div class="form-text" id="promotionCodeHint"></div>
                                </div>
                                <div class="col-md-4">
                                    <input type="hidden" id="points_used" name="DiemSuDung" value="{{ old('DiemSuDung', 0) }}">
                                    <input type="hidden" name="use_points" value="0">
                                    <div class="rounded-3 border bg-white p-2">
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" role="switch" id="use_points" name="use_points" value="1" @checked((bool) old('use_points', false))>
                                            <label class="form-check-label fw-semibold" for="use_points">Dùng điểm tích lũy</label>
                                        </div>
                                        <div class="form-text mt-1" id="pointsToggleStatus" aria-live="polite">Đang tắt — không trừ điểm của khách.</div>
                                        <div class="form-text" id="customerPointsText">Chọn khách hàng để xem số điểm hiện có.</div>
                                    </div>
                                    @error('DiemSuDung')<div class="text-danger small">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ======== Bảng tổng kết ======== --}}
                <div class="col-12">
                    <div class="card border-0 bg-light">
                        <div class="card-body">
                            <div class="row justify-content-end">
                                <div class="col-md-6">
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            <tr>
                                                <td>Tạm tính:</td>
                                                <td class="text-end"><span id="subtotalAmount">0</span> VNĐ</td>
                                            </tr>
                                            <tr id="promotionDiscountRow" class="d-none">
                                                <td>Tiền giảm voucher:</td>
                                                <td class="text-end text-success">-<span id="promotionDiscount">0</span> VNĐ</td>
                                            </tr>
                                            <tr id="pointsDiscountRow" class="d-none">
                                                <td>Tiền giảm do điểm:</td>
                                                <td class="text-end text-success">-<span id="pointsDiscount">0</span> VNĐ</td>
                                            </tr>
                                            <tr class="table-light">
                                                <td class="fw-bold fs-5">TỔNG THANH TOÁN:</td>
                                                <td class="text-end fw-bold fs-5 text-primary"><span id="grandTotal">0</span> VNĐ</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12"><label class="form-label" for="notes">Ghi chú</label><textarea class="form-control" id="notes" name="GhiChu" rows="3">{{ old('GhiChu') }}</textarea></div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4"><button type="reset" class="btn btn-outline-secondary">Làm mới</button><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Lưu đơn hàng</button></div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/customer-autocomplete.js') }}"></script>
<script>
(function () {
    const POINT_VALUE = {{ App\Services\OrderService::POINT_VALUE }};
    const minimumWeight = @json($minimumWeight);
    const prices = {!! $pricesJson !!};
    const unitLabels = @json($unitLabels);
    const customerPoints = {!! $customerPointsJson !!};
    const services = @json($services->mapWithKeys(fn ($service) => [
        $service->DichVuID => ['name' => $service->TenDichVu, 'category_id' => $service->LoaiDichVuID],
    ]));
    const garments = @json($garmentOptions->pluck('TenLoaiDoGiat', 'LoaiDoGiatID'));
    const serviceCategories = @json($serviceCategories->pluck('TenLoaiDichVu', 'LoaiDichVuID'));

    const table = document.getElementById('itemsTable');
    const promotionSelect = document.getElementById('promotion_id');
    const promotionCodeInput = document.getElementById('promotion_code');
    const promotionCodeHint = document.getElementById('promotionCodeHint');
    const pointsInput = document.getElementById('points_used');
    const pointsToggle = document.getElementById('use_points');
    const pointsText = document.getElementById('customerPointsText');
    const pointsToggleStatus = document.getElementById('pointsToggleStatus');
    const customerSelect = document.getElementById('customer_id');

    // Danh sách mã voucher hợp lệ để tra cứu khi người dùng gõ tay mã code.
    const promotionCodes = {};
    Array.from(promotionSelect.options).forEach(option => {
        if (option.dataset.code) promotionCodes[option.dataset.code.trim().toLowerCase()] = option.value;
    });

    const out = {
        subtotal: document.getElementById('subtotalAmount'),
        promotion: document.getElementById('promotionDiscount'),
        promotionRow: document.getElementById('promotionDiscountRow'),
        points: document.getElementById('pointsDiscount'),
        pointsRow: document.getElementById('pointsDiscountRow'),
        grand: document.getElementById('grandTotal')
    };

    const fmt = value => new Intl.NumberFormat('vi-VN').format(Math.round(Number(value) || 0));

    function selectedPricing(row) {
        const service = row.querySelector('.item-service').value;
        const garment = row.querySelector('.item-garment').value;
        return prices.find(price => String(price.service_id) === service && String(price.garment_id) === garment) || null;
    }

    function syncGarmentOptions(row) {
        const serviceId = row.querySelector('.item-service').value;
        const garmentSelect = row.querySelector('.item-garment');
        const selectedGarmentId = garmentSelect.value;
        const eligibleGarmentIds = new Set(
            prices
                .filter(price => String(price.service_id) === serviceId)
                .map(price => String(price.garment_id))
        );

        garmentSelect.replaceChildren(new Option('Chọn loại đồ', ''));
        Object.entries(garments).forEach(([id, name]) => {
            if (!eligibleGarmentIds.has(String(id))) return;

            const option = new Option(name, id);
            garmentSelect.add(option);
        });

        if (eligibleGarmentIds.has(selectedGarmentId)) {
            garmentSelect.value = selectedGarmentId;
            return;
        }

        garmentSelect.value = '';
        row.querySelector('.item-unit-value').value = '';
        row.querySelector('.item-unit').textContent = '—';
        row.querySelector('.item-price').value = '0';
        row.querySelector('.item-subtotal').value = '0';
        row.dataset.dvt = '';
        delete row.querySelector('.item-price').dataset.autoFilled;
    }

    function updateRowState(row, refreshPrice = false) {
        const match = selectedPricing(row);
        const priceInput = row.querySelector('.item-price');
        const quantityInput = row.querySelector('.item-quantity');
        const weightInput = row.querySelector('.item-weight');
        const unitLabel = row.querySelector('.item-unit');
        const unitValue = row.querySelector('.item-unit-value');
        const unitIdInput = unitValue;
        if (refreshPrice && !match) {
            unitIdInput.value = '';
            row.dataset.dvt = '';
            priceInput.value = '0';
            delete priceInput.dataset.autoFilled;
        }
        const unitId = match?.unit_id || unitIdInput.value;
        const unit = String(match?.unit || unitLabels[unitId] || row.dataset.dvt || '').trim();
        const isKg = ['kg', 'kgs', 'kilogram'].includes(unit.toLowerCase());

        const service = row.querySelector('.item-service').value;
        const garment = row.querySelector('.item-garment').value;
        unitLabel.textContent = unit || '—';
        unitValue.value = unitId || '';
        row.querySelector('.item-service').selectedOptions[0]?.setAttribute('data-dvt', unit);
        row.querySelector('.item-service').selectedOptions[0]?.setAttribute('data-dongia', match?.price ?? '');
        row.querySelector('.item-garment').selectedOptions[0]?.setAttribute('data-dvt', unit);
        row.querySelector('.item-garment').selectedOptions[0]?.setAttribute('data-dongia', match?.price ?? '');
        if (!match) {
            row.querySelector('.item-service').selectedOptions[0]?.removeAttribute('data-dvt');
            row.querySelector('.item-service').selectedOptions[0]?.removeAttribute('data-dongia');
            row.querySelector('.item-garment').selectedOptions[0]?.removeAttribute('data-dvt');
            row.querySelector('.item-garment').selectedOptions[0]?.removeAttribute('data-dongia');
        }

        quantityInput.disabled = isKg;
        quantityInput.required = !isKg;
        if (isKg) {
            quantityInput.value = '1';
        }

        weightInput.disabled = !isKg;
        weightInput.required = isKg;
        if (!isKg) {
            weightInput.value = '0';
        }

        if (match && (refreshPrice || priceInput.dataset.autoFilled !== '1')) {
            if (refreshPrice || !priceInput.value || Number(priceInput.value) === 0) {
                priceInput.value = match.price;
            }
            priceInput.dataset.autoFilled = '1';
        }

        return { match, isKg };
    }

    function calculateRowTotal(row) {
        const { isKg } = updateRowState(row);
        const price = Number(row.querySelector('.item-price').value) || 0;
        const quantity = Math.max(1, Number(row.querySelector('.item-quantity').value) || 1);
        const weight = Math.max(0, Number(row.querySelector('.item-weight').value) || 0);
        const amount = Math.round(price * (isKg ? Math.max(weight, minimumWeight) : quantity));
        row.querySelector('.item-subtotal').value = amount;
        return amount;
    }

    function updateRow(row, refreshPrice = false) {
        updateRowState(row, refreshPrice);
        return calculateRowTotal(row);
    }

    function promotionDiscountFor(subtotal) {
        const option = promotionSelect.options[promotionSelect.selectedIndex];
        if (!option || !option.value) return 0;

        const type = option.dataset.type;
        const value = Number(option.dataset.value) || 0;
        const minOrder = Number(option.dataset.min) || 0;
        const maxDiscount = Number(option.dataset.max) || 0;

        if (subtotal < minOrder) return 0;

        let discount = type === 'Phần trăm' ? subtotal * (value / 100) : value;
        if (maxDiscount > 0 && discount > maxDiscount) discount = maxDiscount;

        return Math.min(discount, subtotal);
    }

    function updateCustomerPointsHint() {
        const id = customerSelect.value;
        const available = id ? (customerPoints[id] || 0) : 0;
        pointsText.textContent = `Khách đang có ${fmt(available)} điểm (tương đương ${fmt(available * POINT_VALUE)} VNĐ)`;
    }

    function updateTotals() {
        let subtotal = 0;
        table.querySelectorAll('tbody tr').forEach(row => { subtotal += updateRow(row); });

        const promoDiscount = promotionDiscountFor(subtotal);
        const remaining = Math.max(0, subtotal - promoDiscount);

        const available = customerSelect.value ? (customerPoints[customerSelect.value] || 0) : 0;
        const redeemablePoints = Math.floor(remaining / POINT_VALUE);
        const usedPoints = pointsToggle.checked
            ? Math.max(0, Math.min(available, redeemablePoints))
            : 0;
        pointsInput.value = usedPoints;
        pointsToggleStatus.textContent = pointsToggle.checked
            ? `Đang bật — dự kiến dùng ${fmt(usedPoints)} điểm.`
            : 'Đang tắt — không trừ điểm của khách.';
        const pointsDiscount = usedPoints * POINT_VALUE;

        out.subtotal.textContent = fmt(subtotal);
        out.promotion.textContent = fmt(promoDiscount);
        out.promotionRow.classList.toggle('d-none', promoDiscount <= 0);
        out.points.textContent = fmt(pointsDiscount);
        out.pointsRow.classList.toggle('d-none', pointsDiscount <= 0);
        out.grand.textContent = fmt(Math.max(0, subtotal - promoDiscount - pointsDiscount));
    }

    table.addEventListener('input', event => {
        if (event.target.closest('tr')) updateTotals();
    });
    table.addEventListener('change', event => {
        const row = event.target.closest('tr');
        if (!row) return;
        if (event.target.matches('.item-service')) {
            syncGarmentOptions(row);
        }
        const isSelection = event.target.matches('.item-service, .item-garment');
        updateRow(row, isSelection);
        updateTotals();
    });
    pointsToggle.addEventListener('change', () => {
        updateCustomerPointsHint();
        updateTotals();
    });
    promotionSelect.addEventListener('change', () => {
        const option = promotionSelect.options[promotionSelect.selectedIndex];
        promotionCodeInput.value = (option && option.value) ? option.dataset.code : '';
        promotionCodeHint.textContent = '';
        promotionCodeHint.className = 'form-text';
        updateTotals();
    });
    promotionCodeInput.addEventListener('input', () => {
        const code = promotionCodeInput.value.trim().toLowerCase();
        if (code === '') {
            promotionSelect.value = '';
            promotionCodeHint.textContent = '';
            promotionCodeHint.className = 'form-text';
        } else if (promotionCodes[code]) {
            promotionSelect.value = promotionCodes[code];
            promotionCodeHint.textContent = '✓ Mã voucher hợp lệ';
            promotionCodeHint.className = 'form-text text-success';
        } else {
            // Mã không tồn tại: bỏ chọn voucher để khớp với kết quả server.
            promotionSelect.value = '';
            promotionCodeHint.textContent = '✗ Mã voucher không tồn tại';
            promotionCodeHint.className = 'form-text text-danger';
        }
        updateTotals();
    });
    customerSelect.addEventListener('change', () => { updateCustomerPointsHint(); updateTotals(); });

    document.getElementById('addItem').addEventListener('click', () => {
        const index = table.tBodies[0].rows.length;
        const row = table.tBodies[0].insertRow();
        row.innerHTML = `<td><select class="form-select item-service-category" name="items[${index}][service_category_id]"><option value="">Danh mục</option>${Object.entries(serviceCategories).map(([id,name]) => `<option value="${id}">${name}</option>`).join('')}</select></td><td><select class="form-select item-service" name="items[${index}][DichVuID]" required><option value="">Chọn dịch vụ</option>${Object.entries(services).map(([id,service]) => `<option value="${id}" data-category-id="${service.category_id}">${service.name}</option>`).join('')}</select></td><td><select class="form-select item-garment" name="items[${index}][LoaiDoGiatID]" required><option value="">Chọn loại đồ</option></select></td><td><span class="badge text-bg-light item-unit">—</span><input type="hidden" class="item-unit-value" name="items[${index}][DonViTinhID]"></td><td><input type="number" class="form-control item-quantity" name="items[${index}][SoLuong]" value="1" min="1" required></td><td><input type="number" step="0.01" class="form-control item-weight" name="items[${index}][KhoiLuong]" value="0" min="0"></td><td><input type="number" class="form-control item-price" name="items[${index}][DonGia]" value="0" min="0" step="100"></td><td><input type="number" class="form-control item-subtotal" value="0" readonly></td><td><button type="button" class="btn btn-sm btn-outline-danger remove-item" title="Xóa mặt hàng"><i class="bi bi-trash"></i></button></td>`;
        syncGarmentOptions(row);
        updateRowState(row);
        updateTotals();
    });

    // Filter services by their related service category.
    table.addEventListener('change', event => {
        const row = event.target.closest('tr');
        if (!row) return;

        if (event.target.classList.contains('item-service-category')) {
            const categoryId = event.target.value;
            const serviceSelect = row.querySelector('.item-service');
            if (categoryId) {
                // Filter services by category
                const filtered = Object.entries(services).filter(([, service]) => {
                    return String(service.category_id) === categoryId;
                });
                serviceSelect.innerHTML = '<option value="">Chọn dịch vụ</option>' + filtered.map(([id,service]) => `<option value="${id}" data-category-id="${service.category_id}">${service.name}</option>`).join('');
            } else {
                serviceSelect.innerHTML = '<option value="">Chọn dịch vụ</option>' + Object.entries(services).map(([id,service]) => `<option value="${id}" data-category-id="${service.category_id}">${service.name}</option>`).join('');
            }
            serviceSelect.value = '';
            syncGarmentOptions(row);
            row.querySelector('.item-unit-value').value = '';
            row.querySelector('.item-price').value = '0';
            delete row.querySelector('.item-price').dataset.autoFilled;
            updateTotals();
        }
    });

    table.addEventListener('click', event => {
        if (event.target.closest('.remove-item')) {
            event.target.closest('tr').remove();
            updateTotals();
        }
    });

    // Đồng bộ ô nhập mã theo voucher đang chọn sẵn (old input / chỉnh sửa).
    if (!promotionCodeInput.value.trim()) {
        const selected = promotionSelect.options[promotionSelect.selectedIndex];
        if (selected && selected.value) promotionCodeInput.value = selected.dataset.code || '';
    }

    table.querySelectorAll('tbody tr').forEach(row => {
        syncGarmentOptions(row);
        updateRow(row);
    });
    updateCustomerPointsHint();
    updateTotals();
})();
</script>
@endpush