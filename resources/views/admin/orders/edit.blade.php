@extends('layouts.app')

@section('title', 'Chỉnh sửa đơn hàng - Sky Laundry')
@section('page-title', 'Chỉnh sửa đơn hàng')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Đơn hàng #{{ $order->code }}</h5>
            <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Quay lại</a>
        </div>
        @php
            $garmentOptions = $garments ?? $garmentTypes ?? \App\Models\Garment::where('status', 'active')->orderBy('name')->get();
            $employeeOptions = $employees ?? $staffUsers ?? \App\Models\User::where('role', 'staff')->orderBy('name')->get();
            $priceOptions = $pricings ?? $priceLists ?? \App\Models\Pricing::where('status', 'active')->get();
            $items = old('items');
            if (!is_array($items)) {
                $items = $order->items->map(fn($item) => [
                    'service_id' => $item->service_id,
                    'garment_id' => $item->garment_id,
                    'quantity' => $item->quantity,
                    'weight' => $item->weight !== null ? (float) $item->weight : 0,
                    'price' => (int) round((float) $item->price),
                    'subtotal' => (int) round((float) $item->subtotal),
                    'service_category_id' => $item->service?->category_id,
                    'garment_category_id' => $item->garment?->category_id,
                ])->values()->all();
            }
            if (empty($items)) {
                $items = [['service_id' => '', 'garment_id' => '', 'quantity' => 1, 'weight' => 0, 'price' => 0, 'subtotal' => 0]];
            }
            $pricesJson = $priceOptions->map(fn($p) => ['service_id' => $p->service_id, 'garment_id' => $p->garment_id, 'price' => $p->price, 'unit' => $p->unit])->values()->toJson();
            $customerPointsJson = $customers->mapWithKeys(fn($c) => [$c->id => (int) $c->points])->toJson();

            // Controller có thể trả danh mục dưới dạng model (service_categories)
            // hoặc mảng chuỗi (category của garments). Chuẩn hoá về {id, name}.
            $normalizeCategories = function ($categories) {
                return collect($categories ?? [])->map(function ($category) {
                    if (is_object($category) && isset($category->id)) {
                        return $category;
                    }

                    $value = is_object($category) ? ($category->id ?? $category->name) : $category;

                    return (object) ['id' => $value, 'name' => $value, 'icon' => is_object($category) ? ($category->icon ?? null) : null];
                })->values();
            };
            $serviceCategories = $normalizeCategories($serviceCategories ?? []);
            $garmentCategories = $normalizeCategories($garmentCategories ?? []);
            $units = collect($units ?? []);
        @endphp
        <form action="{{ route('orders.update', $order->id) }}" method="POST" id="orderForm">
            @csrf
            @method('PUT')
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label" for="code">Mã đơn hàng <span class="text-danger ms-1">*</span></label>
                    <input type="text" class="form-control" id="code" name="code" value="{{ old('code', $order->code) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="customer_id">Khách hàng <span class="text-danger ms-1">*</span></label>
                    <select class="form-select" id="customer_id" name="customer_id" required>
                        <option value="">-- Chọn khách hàng --</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(old('customer_id', $order->customer_id) == $customer->id)>{{ $customer->name }} ({{ $customer->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="employee_id">Nhân viên phụ trách</label>
                    <select class="form-select" id="employee_id" name="employee_id">
                        <option value="">-- Chọn nhân viên --</option>
                        @foreach($employeeOptions as $employee)
                            <option value="{{ $employee->id }}" @selected(old('employee_id', $order->employee_id) == $employee->id)>{{ $employee->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="status">Trạng thái <span class="text-danger ms-1">*</span></label>
                    <x-admin.status-select
                        name="status"
                        id="status"
                        :options="$statusFlow ?? \App\Enums\OrderStatus::options()"
                        :selected="$order->status"
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
                            <thead class="table-light"><tr><th>Danh mục dịch vụ</th><th>Dịch vụ <span class="text-danger">*</span></th><th>Danh mục đồ giặt</th><th>Loại đồ giặt <span class="text-danger">*</span></th><th>Số lượng <span class="text-danger">*</span></th><th>Khối lượng (kg)</th><th>Đơn giá</th><th>Thành tiền</th><th></th></tr></thead>
                            <tbody>
                                @foreach($items as $index => $item)
                                    <tr>
                                        <td><select class="form-select js-icon-select item-service-category" name="items[{{ $index }}][service_category_id]"><option value="">-- Danh mục --</option>@foreach($serviceCategories as $cat)<option value="{{ $cat->id }}" data-icon="{{ $cat->icon }}" @selected(($item['service_category_id'] ?? '') == $cat->id)>{{ $cat->name }}</option>@endforeach</select><i class="icon-preview" hidden></i></td>
                                        <td><select class="form-select item-service" name="items[{{ $index }}][service_id]" required><option value="">-- Chọn dịch vụ --</option>@foreach($services as $service)<option value="{{ $service->id }}" @selected(($item['service_id'] ?? '') == $service->id)>{{ $service->name }}</option>@endforeach</select></td>
                                        <td><select class="form-select item-garment-category" name="items[{{ $index }}][garment_category_id]"><option value="">-- Danh mục --</option>@foreach($garmentCategories as $cat)<option value="{{ $cat->id }}" @selected(($item['garment_category_id'] ?? '') == $cat->id)>{{ $cat->name }}</option>@endforeach</select></td>
                                        <td><select class="form-select item-garment" name="items[{{ $index }}][garment_id]" required><option value="">-- Chọn loại đồ --</option>@foreach($garmentOptions as $garment)<option value="{{ $garment->id }}" @selected(($item['garment_id'] ?? '') == $garment->id)>{{ $garment->name }}</option>@endforeach</select></td>
                                        <td><input type="number" class="form-control item-quantity" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" min="1" required></td>
                                        <td><input type="number" step="0.01" class="form-control item-weight" name="items[{{ $index }}][weight]" value="{{ $item['weight'] ?? 0 }}" min="0" step="0.01"></td>
                                        <td><input type="number" class="form-control item-price" name="items[{{ $index }}][price]" value="{{ $item['price'] ?? 0 }}" min="0" step="100" data-touched="1"></td>
                                        <td><input type="number" class="form-control item-subtotal" value="{{ $item['subtotal'] ?? 0 }}" readonly></td>
                                        <td><button type="button" class="btn btn-sm btn-outline-danger remove-item"><i class="bi bi-trash"></i></button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="form-text">Dịch vụ có đơn vị <strong>kg</strong> sẽ tính tiền theo <strong>Khối lượng</strong>, các đơn vị khác tính theo <strong>Số lượng</strong>.</div>
                </div>

                {{-- ======== Ưu đãi: Voucher + Điểm tích lũy ======== --}}
                <div class="col-12">
                    <div class="card border-primary">
                        <div class="card-body">
                            <h6 class="mb-3"><i class="fas fa-ticket me-2 text-primary"></i>Ưu đãi áp dụng</h6>
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label" for="promotion_id">Mã giảm giá (Voucher)</label>
                                    <select class="form-select" id="promotion_id" name="promotion_id">
                                        <option value="">-- Không dùng voucher --</option>
                                        @foreach($promotions as $promotion)
                                            <option value="{{ $promotion->id }}"
                                                    data-code="{{ $promotion->code }}"
                                                    data-type="{{ $promotion->discount_type }}"
                                                    data-value="{{ (float) $promotion->discount_value }}"
                                                    data-min="{{ (float) $promotion->min_order_amount }}"
                                                    data-max="{{ (float) ($promotion->max_discount ?? 0) }}"
                                                    @selected(old('promotion_id', $order->promotion_id) == $promotion->id)>
                                                {{ $promotion->name }} ({{ $promotion->code }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <input type="text" class="form-control form-control-sm mt-2" id="promotion_code" name="promotion_code"
                                           value="{{ old('promotion_code', $order->promotion?->code) }}" placeholder="Hoặc nhập mã voucher...">
                                    <div class="form-text" id="promotionCodeHint"></div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="points_used">Số điểm sử dụng</label>
                                    <input type="number" class="form-control" id="points_used" name="points_used" value="{{ old('points_used', $order->points_used) }}" min="0">
                                    <div class="form-text" id="customerPointsText">1 điểm = 1,000 VNĐ</div>
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
                                            <tr>
                                                <td>Tiền giảm voucher:</td>
                                                <td class="text-end text-danger">-<span id="promotionDiscount">0</span> VNĐ</td>
                                            </tr>
                                            <tr>
                                                <td>Tiền giảm do điểm:</td>
                                                <td class="text-end text-danger">-<span id="pointsDiscount">0</span> VNĐ</td>
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

                <div class="col-12">
                    <label class="form-label" for="notes">Ghi chú</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3">{{ old('notes', $order->notes) }}</textarea>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary">Hủy</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Cập nhật</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const POINT_VALUE = {{ App\Services\OrderService::POINT_VALUE }};
    const prices = {!! $pricesJson !!};
    const customerPoints = {!! $customerPointsJson !!};
    const services = @json($services->pluck('name', 'id'));
    const garments = @json($garmentOptions->pluck('name', 'id'));
    const serviceCategories = @json($serviceCategories->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'icon' => $c->icon ?? null])->keyBy('id'));
    const garmentCategories = @json($garmentCategories->pluck('name', 'id'));

    const table = document.getElementById('itemsTable');
    const promotionSelect = document.getElementById('promotion_id');
    const promotionCodeInput = document.getElementById('promotion_code');
    const promotionCodeHint = document.getElementById('promotionCodeHint');
    const pointsInput = document.getElementById('points_used');
    const pointsText = document.getElementById('customerPointsText');
    const customerSelect = document.getElementById('customer_id');

    // Danh sách mã voucher hợp lệ để tra cứu khi người dùng gõ tay mã code.
    const promotionCodes = {};
    Array.from(promotionSelect.options).forEach(option => {
        if (option.dataset.code) promotionCodes[option.dataset.code.trim().toLowerCase()] = option.value;
    });

    const out = {
        subtotal: document.getElementById('subtotalAmount'),
        promotion: document.getElementById('promotionDiscount'),
        points: document.getElementById('pointsDiscount'),
        grand: document.getElementById('grandTotal')
    };

    const fmt = value => new Intl.NumberFormat('vi-VN').format(Math.round(Number(value) || 0));

    function updateRow(row) {
        const service = row.querySelector('.item-service').value;
        const garment = row.querySelector('.item-garment').value;
        const priceInput = row.querySelector('.item-price');
        const match = prices.find(p => String(p.service_id) === service && String(p.garment_id) === garment);

        if (match && priceInput.dataset.touched !== '1') {
            priceInput.value = match.price;
        }

        const price = Number(priceInput.value) || 0;
        const quantity = Math.max(0, Number(row.querySelector('.item-quantity').value) || 0);
        const weight = Math.max(0, Number(row.querySelector('.item-weight').value) || 0);

        // Đơn vị "kg" nhân với khối lượng, đơn vị khác nhân với số lượng
        // (khớp với OrderService::buildItems()).
        const unit = String((match && match.unit) || '').trim().toLowerCase();
        const isKg = ['kg', 'kgs', 'kilogram'].includes(unit);
        const multiplier = isKg ? weight : quantity;

        const amount = price * multiplier;
        row.querySelector('.item-subtotal').value = amount;
        return amount;
    }

    function promotionDiscountFor(subtotal) {
        const option = promotionSelect.options[promotionSelect.selectedIndex];
        if (!option || !option.value) return 0;

        const type = option.dataset.type;
        const value = Number(option.dataset.value) || 0;
        const minOrder = Number(option.dataset.min) || 0;
        const maxDiscount = Number(option.dataset.max) || 0;

        if (subtotal < minOrder) return 0;

        let discount = type === 'percentage' ? subtotal * (value / 100) : value;
        if (maxDiscount > 0 && discount > maxDiscount) discount = maxDiscount;

        return Math.min(discount, subtotal);
    }

    function updateCustomerPointsHint() {
        const id = customerSelect.value;
        const available = id ? (customerPoints[id] || 0) : 0;
        pointsText.textContent = `Khách đang có ${fmt(available)} điểm — 1 điểm = ${fmt(POINT_VALUE)} VNĐ`;
        pointsInput.max = available;
    }

    function updateTotals() {
        let subtotal = 0;
        table.querySelectorAll('tbody tr').forEach(row => { subtotal += updateRow(row); });

        const promoDiscount = promotionDiscountFor(subtotal);
        const remaining = Math.max(0, subtotal - promoDiscount);

        const available = customerSelect.value ? (customerPoints[customerSelect.value] || 0) : 0;
        const usedPoints = Math.max(0, Math.min(Number(pointsInput.value) || 0, available));
        const pointsDiscount = Math.min(usedPoints * POINT_VALUE, remaining);

        out.subtotal.textContent = fmt(subtotal);
        out.promotion.textContent = fmt(promoDiscount);
        out.points.textContent = fmt(pointsDiscount);
        out.grand.textContent = fmt(Math.max(0, subtotal - promoDiscount - pointsDiscount));
    }

    table.addEventListener('input', event => {
        if (event.target.classList.contains('item-price')) event.target.dataset.touched = '1';
        if (event.target.closest('tr')) updateTotals();
    });
    table.addEventListener('change', event => { if (event.target.closest('tr')) updateTotals(); });
    pointsInput.addEventListener('input', updateTotals);
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
        row.innerHTML = `<td><select class="form-select js-icon-select item-service-category" name="items[${index}][service_category_id]"><option value="">-- Danh mục --</option>${Object.entries(serviceCategories).map(([id,cat]) => `<option value="${id}" data-icon="${cat.icon || ''}">${cat.name}</option>`).join('')}</select><i class="icon-preview" hidden></i></td><td><select class="form-select item-service" name="items[${index}][service_id]" required><option value="">-- Chọn dịch vụ --</option>${Object.entries(services).map(([id,name]) => `<option value="${id}">${name}</option>`).join('')}</select></td><td><select class="form-select item-garment-category" name="items[${index}][garment_category_id]"><option value="">-- Danh mục --</option>${Object.entries(garmentCategories).map(([id,name]) => `<option value="${id}">${name}</option>`).join('')}</select></td><td><select class="form-select item-garment" name="items[${index}][garment_id]" required><option value="">-- Chọn loại đồ --</option>${Object.entries(garments).map(([id,name]) => `<option value="${id}">${name}</option>`).join('')}</select></td><td><input type="number" class="form-control item-quantity" name="items[${index}][quantity]" value="1" min="1" required></td><td><input type="number" step="0.01" class="form-control item-weight" name="items[${index}][weight]" value="0" min="0" step="0.01"></td><td><input type="number" class="form-control item-price" name="items[${index}][price]" value="0" min="0" step="100"></td><td><input type="number" class="form-control item-subtotal" value="0" readonly></td><td><button type="button" class="btn btn-sm btn-outline-danger remove-item"><i class="bi bi-trash"></i></button></td>`;
        updateTotals();
    });

    table.addEventListener('click', event => {
        if (event.target.closest('.remove-item')) {
            event.target.closest('tr').remove();
            updateTotals();
        }
    });

    // Đồng bộ ô nhập mã theo voucher đang chọn sẵn (old input / voucher của đơn).
    if (!promotionCodeInput.value.trim()) {
        const selected = promotionSelect.options[promotionSelect.selectedIndex];
        if (selected && selected.value) promotionCodeInput.value = selected.dataset.code || '';
    }

    updateCustomerPointsHint();
    updateTotals();
})();
</script>
@endpush