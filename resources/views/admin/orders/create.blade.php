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

        @endphp
        <div id="order-row-status" class="form-text mb-2" role="status"></div>
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
                    <input type="hidden" name="TrangThai" value="{{ \App\Enums\OrderStatus::Received->value }}">
                    <input type="text" class="form-control" id="status" value="Đã tiếp nhận" readonly>
                    <div class="form-text">Kiểm kê đồ thực tế trước khi lưu. Đơn từ Booking cần tạo trên màn hình kiểm kê Booking.</div>
                </div>
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label mb-0">Các mặt hàng trong đơn</label>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addItem"><i class="bi bi-plus-lg me-1"></i>Thêm mặt hàng</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-2" id="itemsTable">
                            <thead class="table-light"><tr><th scope="col">STT</th><th>Danh mục dịch vụ</th><th>Dịch vụ <span class="text-danger">*</span></th><th>Loại đồ giặt <span class="text-danger">*</span></th><th>ĐVT</th><th>Số lượng</th><th>Khối lượng (kg)</th><th>Đơn giá</th><th>Thành tiền</th><th>Tình trạng trước khi giặt <span class="text-danger">*</span></th><th></th></tr></thead>
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
                                        <td data-row-number>{{ $loop->iteration }}</td>
                                        <td><select class="form-select item-service-category" name="items[{{ $index }}][service_category_id]"><option value="">Danh mục</option>@foreach($serviceCategories as $category)<option value="{{ $category->LoaiDichVuID }}" @selected(($item['service_category_id'] ?? '') == $category->LoaiDichVuID)>{{ $category->TenLoaiDichVu }}</option>@endforeach</select></td>
                                        <td><select class="form-select item-service" name="items[{{ $index }}][DichVuID]" required><option value="">Chọn dịch vụ</option>@foreach($services as $service)<option value="{{ $service->DichVuID }}" data-category-id="{{ $service->LoaiDichVuID }}" @selected(($item['DichVuID'] ?? $item['service_id'] ?? '') == $service->DichVuID)>{{ $service->TenDichVu }}</option>@endforeach</select></td>
                                        <td><select class="form-select item-garment" name="items[{{ $index }}][LoaiDoGiatID]" required><option value="">Chọn loại đồ</option>@foreach($garmentOptions->whereIn('LoaiDoGiatID', $availableGarmentIds) as $garment)<option value="{{ $garment->LoaiDoGiatID }}" @selected(($item['LoaiDoGiatID'] ?? $item['garment_id'] ?? '') == $garment->LoaiDoGiatID)>{{ $garment->TenLoaiDoGiat }}</option>@endforeach</select></td>
                                        <td>
                                            <select class="form-select item-unit-value @error("items.$index.DonViTinhID") is-invalid @enderror" name="items[{{ $index }}][DonViTinhID]" required aria-label="Đơn vị tính">
                                                <option value="">Chọn ĐVT</option>
                                                @foreach($priceOptions->where('DichVuID', $selectedServiceId)->where('LoaiDoGiatID', $item['LoaiDoGiatID'] ?? '') as $pricing)
                                                    <option value="{{ $pricing->DonViTinhID }}" @selected(($item['DonViTinhID'] ?? '') == $pricing->DonViTinhID)>{{ $pricing->donViTinh?->KyHieu ?: $pricing->donViTinh?->TenDonViTinh }}</option>
                                                @endforeach
                                            </select>
                                            @error("items.$index.DonViTinhID")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </td>
                                        <td><input type="number" class="form-control item-quantity" name="items[{{ $index }}][SoLuong]" value="{{ $item['SoLuong'] ?? $item['quantity'] ?? 1 }}" min="1" required></td>
                                        <td><input type="number" step="0.01" class="form-control item-weight" name="items[{{ $index }}][KhoiLuong]" value="{{ $item['KhoiLuong'] ?? $item['weight'] ?? 0 }}" min="0"></td>
                                        <td><input type="number" class="form-control item-price" name="items[{{ $index }}][DonGia]" value="{{ $item['DonGia'] ?? $item['price'] ?? 0 }}" min="0" step="0.01" readonly aria-label="Đơn giá theo bảng giá"></td>
                                        <td><input type="number" class="form-control item-subtotal" value="{{ $item['subtotal'] ?? 0 }}" readonly></td>
                                        <td>
                                            <input type="text" class="form-control @error("items.$index.TinhTrangTruocKhiGiat") is-invalid @enderror" name="items[{{ $index }}][TinhTrangTruocKhiGiat]" value="{{ $item['TinhTrangTruocKhiGiat'] ?? '' }}" maxlength="320" required aria-label="Tình trạng trước khi giặt">
                                            @error("items.$index.TinhTrangTruocKhiGiat")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </td>
                                        <td><button type="button" class="btn btn-sm btn-outline-danger remove-item" title="Xóa mặt hàng"><i class="bi bi-trash"></i></button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="form-text">Đơn giá lấy từ bảng giá hiện hành và không sửa trực tiếp. Dịch vụ có đơn vị <strong>kg</strong> sẽ tính theo khối lượng, tối thiểu {{ number_format($minimumWeight, 1) }} kg; các đơn vị khác tính theo số lượng.</div>
                </div>

                {{-- ======== Ưu đãi và điểm tích lũy ======== --}}
                <div class="col-12">
                    <div class="border rounded-3 bg-light px-3 py-2">
                        <input type="hidden" id="points_used" name="DiemSuDung" value="{{ old('DiemSuDung', 0) }}">
                        <input type="hidden" name="use_points" value="0">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <h6 class="mb-0"><i class="fas fa-ticket me-2 text-primary" aria-hidden="true"></i>Ưu đãi áp dụng</h6>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" role="switch" id="use_points" name="use_points" value="1" aria-describedby="customerPointsText pointsToggleStatus" @checked((bool) old('use_points', false))>
                                <label class="form-check-label fw-semibold" for="use_points">Dùng điểm tích lũy</label>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap justify-content-between gap-1 small text-muted mt-2">
                            <span id="customerPointsText">Chọn khách hàng để xem số điểm hiện có.</span>
                            <span id="pointsToggleStatus" aria-live="polite">Đang tắt — không trừ điểm của khách.</span>
                        </div>
                        @error('DiemSuDung')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        <p class="small text-muted mb-0 mt-1">Đơn tạo trên web không dùng voucher. Đơn chuyển từ Booking giữ voucher của lịch đặt.</p>
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
<script src="{{ asset('assets/js/order-item-pricing.js') }}"></script>
<script>
(function () {
    const POINT_VALUE = {{ App\Services\OrderService::POINT_VALUE }};
    const minimumWeight = @json($minimumWeight);
    const prices = {!! $pricesJson !!};
    const customerPoints = {!! $customerPointsJson !!};
    const services = @json($services->mapWithKeys(fn ($service) => [
        $service->DichVuID => ['name' => $service->TenDichVu, 'category_id' => $service->LoaiDichVuID],
    ]));
    const garments = @json($garmentOptions->pluck('TenLoaiDoGiat', 'LoaiDoGiatID'));
    const serviceCategories = @json($serviceCategories->pluck('TenLoaiDichVu', 'LoaiDichVuID'));

    const table = document.getElementById('itemsTable');
    const pointsInput = document.getElementById('points_used');
    const pointsToggle = document.getElementById('use_points');
    const pointsText = document.getElementById('customerPointsText');
    const pointsToggleStatus = document.getElementById('pointsToggleStatus');
    const customerSelect = document.getElementById('customer_id');

    const out = {
        subtotal: document.getElementById('subtotalAmount'),
        points: document.getElementById('pointsDiscount'),
        pointsRow: document.getElementById('pointsDiscountRow'),
        grand: document.getElementById('grandTotal')
    };

    const fmt = value => new Intl.NumberFormat('vi-VN').format(Math.round(Number(value) || 0));

    const { syncGarmentOptions, updateRowState, updateRow } = window.OrderItemPricing({
        prices, garments, minimumWeight,
    });

    function updateCustomerPointsHint() {
        const id = customerSelect.value;
        const available = id ? (customerPoints[id] || 0) : 0;
        pointsText.textContent = `Khách đang có ${fmt(available)} điểm (tương đương ${fmt(available * POINT_VALUE)} VNĐ)`;
    }

    function updateTotals() {
        let subtotal = 0;
        table.querySelectorAll('tbody tr').forEach((row, index) => {
            row.querySelector('[data-row-number]').textContent = index + 1;
            subtotal += updateRow(row);
        });

        const remaining = subtotal;

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
        out.points.textContent = fmt(pointsDiscount);
        out.pointsRow.classList.toggle('d-none', pointsDiscount <= 0);
        out.grand.textContent = fmt(Math.max(0, subtotal - pointsDiscount));
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
        updateRow(row);
        updateTotals();
    });
    pointsToggle.addEventListener('change', () => {
        updateCustomerPointsHint();
        updateTotals();
    });
    customerSelect.addEventListener('change', () => { updateCustomerPointsHint(); updateTotals(); });

    let nextItemIndex = Math.max(-1, ...Array.from(table.querySelectorAll('[name^="items["]'), input => Number(input.name.match(/^items\[(\d+)\]/)?.[1] ?? -1))) + 1;

    document.getElementById('addItem').addEventListener('click', () => {
        const index = nextItemIndex++;
        const row = table.tBodies[0].insertRow();
        row.innerHTML = `<td data-row-number></td><td><select class="form-select item-service-category" name="items[${index}][service_category_id]"><option value="">Danh mục</option>${Object.entries(serviceCategories).map(([id,name]) => `<option value="${id}">${name}</option>`).join('')}</select></td><td><select class="form-select item-service" name="items[${index}][DichVuID]" required><option value="">Chọn dịch vụ</option>${Object.entries(services).map(([id,service]) => `<option value="${id}" data-category-id="${service.category_id}">${service.name}</option>`).join('')}</select></td><td><select class="form-select item-garment" name="items[${index}][LoaiDoGiatID]" required><option value="">Chọn loại đồ</option></select></td><td><select class="form-select item-unit-value" name="items[${index}][DonViTinhID]" required aria-label="Đơn vị tính"><option value="">Chọn ĐVT</option></select></td><td><input type="number" class="form-control item-quantity" name="items[${index}][SoLuong]" value="1" min="1" required></td><td><input type="number" step="0.01" class="form-control item-weight" name="items[${index}][KhoiLuong]" value="0" min="0"></td><td><input type="number" class="form-control item-price" name="items[${index}][DonGia]" value="0" min="0" step="0.01" readonly aria-label="Đơn giá theo bảng giá"></td><td><input type="number" class="form-control item-subtotal" value="0" readonly></td><td><input type="text" class="form-control" name="items[${index}][TinhTrangTruocKhiGiat]" maxlength="320" required aria-label="Tình trạng trước khi giặt"></td><td><button type="button" class="btn btn-sm btn-outline-danger remove-item" title="Xóa mặt hàng"><i class="bi bi-trash"></i></button></td>`;
        syncGarmentOptions(row);
        updateRowState(row);
        updateTotals();
        document.getElementById('order-row-status').textContent = `Đã thêm dòng ${table.tBodies[0].rows.length}. Hãy chọn dịch vụ và loại đồ.`;
        row.querySelector('.item-service-category').focus();
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
            updateTotals();
        }
    });

    table.addEventListener('click', event => {
        if (event.target.closest('.remove-item')) {
            event.target.closest('tr').remove();
            updateTotals();
        }
    });

    table.querySelectorAll('tbody tr').forEach(row => {
        syncGarmentOptions(row);
        updateRow(row);
    });
    updateCustomerPointsHint();
    updateTotals();
})();
</script>
@endpush
