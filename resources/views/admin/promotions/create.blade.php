@extends('layouts.app')

@section('title', 'Thêm khuyến mãi - Sky Laundry')
@section('page-title', 'Thêm khuyến mãi')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Thông tin chương trình</h5>
            <a href="{{ route('promotions.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('promotions.store') }}" method="POST">
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label" for="name">Tên chương trình <span class="text-danger ms-1">*</span></label>
                    <input type="text" id="name" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" placeholder="VD: Giảm 20% đơn đầu" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="code">Mã khuyến mãi <span class="text-danger ms-1">*</span></label>
                    <input type="text" id="code" class="form-control @error('code') is-invalid @enderror" name="code" value="{{ old('code') }}" placeholder="VD: PROMO20" pattern="[A-Za-z0-9_-]+" required>
                    <div class="form-text">Chỉ gồm chữ, số, gạch ngang và gạch dưới (không dấu, không khoảng trắng).</div>
                    @error('code')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="discount_type">Loại giảm <span class="text-danger ms-1">*</span></label>
                    <select class="form-select @error('discount_type') is-invalid @enderror" name="discount_type" id="discount_type" required>
                        <option value="">-- Chọn loại giảm --</option>
                        @foreach(\App\Models\Promotion::discountTypeOptions() as $value => $label)
                            <option value="{{ $value }}" @selected(old('discount_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('discount_type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="discount_value">Giá trị giảm <span class="text-danger ms-1">*</span></label>
                    <input type="number" step="0.01" min="0" id="discount_value" class="form-control @error('discount_value') is-invalid @enderror" name="discount_value" value="{{ old('discount_value') }}" placeholder="20 hoặc 50000" required>
                    <div class="form-text" id="discountValueHint">Phần trăm: 1–100. Số tiền cố định: từ 1.000 VNĐ.</div>
                    <div class="invalid-feedback" id="discountValueError"></div>
                    @error('discount_value')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="max_discount">Giảm tối đa</label>
                    <input type="number" step="1000" min="0" id="max_discount" class="form-control @error('max_discount') is-invalid @enderror" name="max_discount" value="{{ old('max_discount') }}" placeholder="Để trống = không giới hạn">
                    <div class="form-text" id="maxDiscountHint">Chỉ dùng khi loại giảm là Phần trăm (VD: 20% tối đa 50.000đ).</div>
                    @error('max_discount')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="min_order_amount">Đơn hàng tối thiểu</label>
                    <input type="number" step="1000" min="1" id="min_order_amount" class="form-control @error('min_order_amount') is-invalid @enderror" name="min_order_amount" value="{{ old('min_order_amount') }}" placeholder="Để trống = không yêu cầu">
                    <div class="form-text">Để trống = không yêu cầu. Nếu nhập thì từ 1 VNĐ trở lên (VD: 200000).</div>
                    @error('min_order_amount')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="usage_limit">Số lượt sử dụng</label>
                    <input type="number" min="1" id="usage_limit" class="form-control @error('usage_limit') is-invalid @enderror" name="usage_limit" value="{{ old('usage_limit') }}" placeholder="Để trống = không giới hạn">
                    @error('usage_limit')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="quantity">Số mã phát ra</label>
                    <input type="number" min="1" id="quantity" class="form-control @error('quantity') is-invalid @enderror" name="quantity" value="{{ old('quantity') }}" placeholder="Để trống = phát vô hạn">
                    <div class="form-text">Số lượt tối đa được phát mã của chương trình này.</div>
                    @error('quantity')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="starts_at">Ngày bắt đầu</label>
                    <input type="date" id="starts_at" class="form-control @error('starts_at') is-invalid @enderror" name="starts_at" value="{{ old('starts_at') }}">
                    <div class="form-text">Để trống = có hiệu lực ngay.</div>
                    @error('starts_at')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="expires_at">Ngày hết hạn</label>
                    <input type="date" id="expires_at" class="form-control @error('expires_at') is-invalid @enderror" name="expires_at" value="{{ old('expires_at') }}">
                    <div class="form-text">Để trống = không giới hạn thời gian.</div>
                    @error('expires_at')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="status">Trạng thái <span class="text-danger ms-1">*</span></label>
                    <x-admin.status-select
                        name="status"
                        id="status"
                        :options="\App\Enums\RecordStatus::options()"
                        selected="active"
                        class="form-select @error('status') is-invalid @enderror"
                    />
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="conditions[{{ \App\Models\Promotion::CONDITION_FIRST_ORDER_ONLY }}]" id="first_order_only" value="1" {{ old('conditions.' . \App\Models\Promotion::CONDITION_FIRST_ORDER_ONLY) ? 'checked' : '' }}>
                        <label class="form-check-label" for="first_order_only">
                            Chỉ áp dụng cho đơn đầu tiên của khách
                        </label>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('promotions.index') }}" class="btn btn-outline-secondary">Hủy</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Lưu khuyến mãi
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (() => {
        const typeSelect = document.getElementById('discount_type');
        const valueInput = document.getElementById('discount_value');
        const hint = document.getElementById('discountValueHint');
        const error = document.getElementById('discountValueError');
        const maxInput = document.getElementById('max_discount');
        const maxHint = document.getElementById('maxDiscountHint');
        if (!typeSelect || !valueInput) return;

        // Ràng buộc theo loại giảm: Phần trăm 1–100, Số tiền cố định từ 1.000đ
        const RULES = {
            percentage: { min: 1, max: 100, step: 1, placeholder: '20', hint: 'Phần trăm: nhập số từ 1 đến 100 (VD: 20 = giảm 20%).' },
            fixed: { min: 1000, max: null, step: 1000, placeholder: '50000', hint: 'Số tiền cố định: từ 1.000 VNĐ trở lên (VD: 50000 = giảm 50.000đ).' }
        };

        const MAX_DISCOUNT_HINT = 'Chỉ dùng khi loại giảm là Phần trăm (VD: 20% tối đa 50.000đ).';
        const MAX_DISCOUNT_LOCKED_HINT = 'Không áp dụng khi loại giảm là Số tiền cố định — trường này sẽ bị bỏ qua.';
        let maxDiscountBackup = null;

        function currentValue() {
            return Number(valueInput.value) || 0;
        }

        // "Giảm tối đa" chỉ có ý nghĩa với loại giảm Phần trăm: khóa + bỏ qua khi loại khác
        function syncMaxDiscount() {
            if (!maxInput) return;

            if (typeSelect.value === 'percentage') {
                maxInput.disabled = false;
                maxInput.min = '1000';
                maxInput.step = '1000';
                maxInput.placeholder = 'Để trống = không giới hạn';
                if (maxHint) maxHint.textContent = MAX_DISCOUNT_HINT;

                if (maxInput.value.trim() === '' && maxDiscountBackup !== null) {
                    maxInput.value = maxDiscountBackup;
                }
                maxDiscountBackup = null;

                const maxValue = Number(maxInput.value) || 0;
                if (maxValue > 0 && maxValue < 1000) {
                    maxInput.value = 1000;
                }
                return;
            }

            if (maxInput.value.trim() !== '') {
                maxDiscountBackup = maxInput.value;
            }
            maxInput.disabled = true;
            maxInput.value = '';
            maxInput.classList.remove('is-invalid');
            if (maxHint) maxHint.textContent = MAX_DISCOUNT_LOCKED_HINT;
        }

        function applyType(keepValidValue = true) {
            const rule = RULES[typeSelect.value] || null;
            syncMaxDiscount();

            if (!rule) {
                valueInput.removeAttribute('min');
                valueInput.removeAttribute('max');
                valueInput.step = '0.01';
                valueInput.placeholder = '20 hoặc 50000';
                hint.textContent = 'Phần trăm: 1–100. Số tiền cố định: từ 1.000 VNĐ.';
                valueInput.classList.remove('is-invalid');
                error.textContent = '';
                return;
            }

            valueInput.min = rule.min;
            valueInput.step = rule.step;
            if (rule.max === null) {
                valueInput.removeAttribute('max');
            } else {
                valueInput.max = rule.max;
            }
            valueInput.placeholder = rule.placeholder;
            hint.textContent = rule.hint;

            const value = currentValue();
            if (keepValidValue && value > 0 && value >= rule.min && (rule.max === null || value <= rule.max)) {
                return;
            }

            // Chưa chọn loại giảm -> chỉ gợi ý mốc tối thiểu, không tự điền số
            if (valueInput.value.trim() === '' && !keepValidValue) {
                return;
            }

            if (value > 0 || valueInput.value.trim() !== '') {
                valueInput.value = rule.min;
            }
            valueInput.classList.remove('is-invalid');
            error.textContent = '';
        }

        typeSelect.addEventListener('change', () => applyType(true));
        maxInput && maxInput.addEventListener('input', () => {
            const maxValue = Number(maxInput.value) || 0;
            if (maxValue > 0 && maxValue < 1000) {
                maxInput.classList.add('is-invalid');
            } else {
                maxInput.classList.remove('is-invalid');
            }
        });
        valueInput.addEventListener('input', () => {
            const rule = RULES[typeSelect.value] || null;
            if (!rule) return;
            const value = currentValue();
            if (value > 0 && (value < rule.min || (rule.max !== null && value > rule.max))) {
                valueInput.classList.add('is-invalid');
                error.textContent = rule.max === null
                    ? `Giá trị giảm cố định tối thiểu là ${rule.min.toLocaleString('vi-VN')} VNĐ.`
                    : `Giá trị giảm phần trăm phải từ ${rule.min} đến ${rule.max}.`;
            } else {
                valueInput.classList.remove('is-invalid');
                error.textContent = '';
            }
        });

        // Áp ràng buộc ngay khi mở form (kể cả khi vừa submit sai và quay lại)
        applyType(true);
    })();
</script>
@endpush
