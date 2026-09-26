@extends('layouts.app')

@section('title', 'Thêm dịch vụ - Sky Laundry')
@section('page-title', 'Thêm dịch vụ')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Thông tin dịch vụ</h5>
            <a href="{{ route('services.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('services.store') }}" method="POST">
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Danh mục</label>
                    <select class="form-select @error('service_category_id') is-invalid @enderror" name="service_category_id">
                        <option value="">Không có</option>
                        @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('service_category_id') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('service_category_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tên dịch vụ <span class="text-danger ms-1">*</span></label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" placeholder="Nhập tên dịch vụ" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Loại dịch vụ</label>
                    <select class="form-select @error('type') is-invalid @enderror" name="type">
                        <option value="wash" @selected(old('type') === 'wash')>Giặt</option>
                        <option value="dry_clean" @selected(old('type') === 'dry_clean')>Giặt khô</option>
                        <option value="iron" @selected(old('type') === 'iron')>Ủi</option>
                        <option value="blanket" @selected(old('type') === 'blanket')>Chăn mền</option>
                        <option value="shoes" @selected(old('type') === 'shoes')>Giày dép</option>
                        <option value="express" @selected(old('type') === 'express')>Giao nhanh</option>
                    </select>
                    @error('type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Thời gian xử lý (Giờ)</label>
                    <input type="number" class="form-control @error('processing_time') is-invalid @enderror" name="processing_time" value="{{ old('processing_time', 24) }}" min="1" placeholder="24">
                    <div class="form-text">Nhập số giờ xử lý, ví dụ: 2 = 2 giờ, 24 = 1 ngày, 48 = 2 ngày</div>
                    @error('processing_time')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Icon FontAwesome</label>
                    <div class="input-group">
                        <input type="text" class="form-control @error('icon') is-invalid @enderror" name="icon" id="iconInput" value="{{ old('icon') }}" placeholder="fa-solid fa-washer" readonly>
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-chevron-down"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end w-100" style="max-height: 300px; overflow-y: auto;">
                            <li><input type="text" class="form-control form-control-sm px-2 py-1" placeholder="Tìm icon..." id="iconSearch"></li>
                            <li><hr class="dropdown-divider"></li>
                            @foreach([
                                'fa-solid fa-washer' => 'Máy giặt',
                                'fa-solid fa-tshirt' => 'Áo',
                                'fa-solid fa-shirt' => 'Sơ mi',
                                'fa-solid fa-socks' => 'Tất',
                                'fa-solid fa-hat-cowboy' => 'Mũ',
                                'fa-solid fa-shoe-prints' => 'Giày',
                                'fa-solid fa-bath' => 'Tắm',
                                'fa-solid fa-droplet' => 'Nước',
                                'fa-solid fa-sparkles' => 'Làm sạch',
                                'fa-solid fa-wind' => 'Sấy',
                                'fa-solid fa-iron' => 'Ủi',
                                'fa-solid fa-blanket' => 'Chăn',
                                'fa-solid fa-bed' => 'Giường',
                                'fa-solid fa-soap' => 'Xà phòng',
                                'fa-solid fa-spray-can' => 'Phun',
                                'fa-solid fa-broom' => 'Chổi',
                                'fa-solid fa-mop' => 'Cải',
                                'fa-solid fa-truck' => 'Giao hàng',
                                'fa-solid fa-box' => 'Đóng gói',
                                'fa-solid fa-tag' => 'Giá',
                                'fa-solid fa-clock' => 'Thời gian',
                                'fa-solid fa-calendar' => 'Lịch',
                                'fa-solid fa-star' => 'Yêu thích',
                                'fa-solid fa-heart' => 'Tình yêu',
                                'fa-solid fa-gem' => 'Cao cấp',
                                'fa-solid fa-certificate' => 'Chứng nhận',
                                'fa-solid fa-award' => 'Giải thưởng',
                                'fa-solid fa-medal' => 'Huy chương',
                                'fa-solid fa-trophy' => 'Cúp',
                                'fa-solid fa-crown' => 'Vương',
                                'fa-solid fa-magic' => 'Phép thuật',
                            ] as $class => $label)
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2 icon-picker-item" href="#" data-icon="{{ $class }}">
                                    <i class="{{ $class }} fa-lg"></i>
                                    <span>{{ $label }}</span>
                                </a>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @error('icon')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Icon hiện tại: <i id="iconPreview" class="{{ old('icon', 'fa-solid fa-washer') }}"></i></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Giá cơ bản <span class="text-danger ms-1">*</span></label>
                    <input type="number" class="form-control @error('price') is-invalid @enderror" name="price" value="{{ old('price') }}" placeholder="25000" min="0" required>
                    @error('price')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Đơn vị tính</label>
                    <select class="form-select @error('unit') is-invalid @enderror" name="unit">
                        <option value="kg" @selected(old('unit') === 'kg' || old('unit') === null)>kg</option>
                        <option value="món" @selected(old('unit') === 'món')>món</option>
                        <option value="combo" @selected(old('unit') === 'combo')>combo</option>
                        <option value="đôi" @selected(old('unit') === 'đôi')>đôi</option>
                        <option value="cái" @selected(old('unit') === 'cái')>cái</option>
                    </select>
                    @error('unit')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Trạng thái</label>
                    <select class="form-select @error('status') is-invalid @enderror" name="status">
                        <option value="active" @selected(old('status', 'active') === 'active')>Đang hoạt động</option>
                        <option value="inactive" @selected(old('status') === 'inactive')>Tạm ngưng</option>
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Mô tả chi tiết</label>
                    <textarea class="form-control @error('description') is-invalid @enderror" name="description" rows="3" placeholder="Nhập mô tả chi tiết về dịch vụ...">{{ old('description') }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="reset" class="btn btn-outline-secondary">Làm mới</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Lưu dịch vụ
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const iconInput = document.getElementById('iconInput');
        const iconPreview = document.getElementById('iconPreview');
        const iconSearch = document.getElementById('iconSearch');
        const dropdownItems = document.querySelectorAll('.icon-picker-item');

        // Select icon
        dropdownItems.forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const iconClass = this.dataset.icon;
                iconInput.value = iconClass;
                iconPreview.className = iconClass;
                
                // Close dropdown
                const dropdown = bootstrap.Dropdown.getInstance(this.closest('.dropdown-menu').previousElementSibling);
                if (dropdown) dropdown.hide();
            });
        });

        // Search filter
        iconSearch.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            dropdownItems.forEach(item => {
                const label = item.querySelector('span').textContent.toLowerCase();
                const iconClass = item.dataset.icon.toLowerCase();
                if (label.includes(query) || iconClass.includes(query)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });
</script>
@endpush
