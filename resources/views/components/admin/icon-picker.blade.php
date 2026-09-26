@props([
    'name' => 'icon',
    'value' => null,
    'label' => 'Icon',
    'default' => 'fa-solid fa-washer',
    'id' => null,
])

{{--
    Bộ chọn icon trực quan (FontAwesome) dùng chung cho các form Tạo / Chỉnh sửa.
    Chủ cửa hàng chỉ cần bấm chọn icon trong dropdown, không phải nhớ class FontAwesome.

    Thứ tự ưu tiên giá trị đang chọn: old() -> $value -> $default.
--}}
@php
    $uid = $id ?: 'icon-picker-' . uniqid();
    $current = old($name, $value) ?: $default;
    $icons = [
        'fa-solid fa-washer' => 'Máy giặt',
        'fa-solid fa-tshirt' => 'Áo phông',
        'fa-solid fa-shirt' => 'Sơ mi',
        'fa-solid fa-vest' => 'Vest',
        'fa-solid fa-person-dress' => 'Váy',
        'fa-solid fa-socks' => 'Tất',
        'fa-solid fa-hat-cowboy' => 'Mũ',
        'fa-solid fa-shoe-prints' => 'Giày dép',
        'fa-solid fa-bath' => 'Tắm',
        'fa-solid fa-soap' => 'Xà phòng',
        'fa-solid fa-spray-can' => 'Phun xịt',
        'fa-solid fa-broom' => 'Chổi',
        'fa-solid fa-mop' => 'Cải lau',
        'fa-solid fa-droplet' => 'Nước',
        'fa-solid fa-sparkles' => 'Làm sạch',
        'fa-solid fa-wind' => 'Sấy',
        'fa-solid fa-iron' => 'Ủi',
        'fa-solid fa-blanket' => 'Chăn mền',
        'fa-solid fa-bed' => 'Giường',
        'fa-solid fa-couch' => 'Nội thất',
        'fa-solid fa-house' => 'Nhà cửa',
        'fa-solid fa-building' => 'Tòa nhà',
        'fa-solid fa-store' => 'Cửa hàng',
        'fa-solid fa-industry' => 'Nhà máy',
        'fa-solid fa-warehouse' => 'Kho',
        'fa-solid fa-truck' => 'Giao hàng',
        'fa-solid fa-truck-fast' => 'Giao nhanh',
        'fa-solid fa-box' => 'Đóng gói',
        'fa-solid fa-bag-shopping' => 'Túi',
        'fa-solid fa-basket-shopping' => 'Giỏ hàng',
        'fa-solid fa-tag' => 'Giá / Thẻ',
        'fa-solid fa-percent' => 'Giảm giá',
        'fa-solid fa-coins' => 'Tiền',
        'fa-solid fa-money-bill' => 'Tiền mặt',
        'fa-solid fa-clock' => 'Thời gian',
        'fa-solid fa-calendar' => 'Lịch',
        'fa-solid fa-bolt' => 'Nhanh',
        'fa-solid fa-fire' => 'Nóng',
        'fa-solid fa-snowflake' => 'Lạnh',
        'fa-solid fa-leaf' => 'Tự nhiên',
        'fa-solid fa-recycle' => 'Tái chế',
        'fa-solid fa-star' => 'Yêu thích',
        'fa-solid fa-star-half-stroke' => 'Đánh giá',
        'fa-solid fa-heart' => 'Tình yêu',
        'fa-solid fa-gem' => 'Cao cấp',
        'fa-solid fa-certificate' => 'Chứng nhận',
        'fa-solid fa-award' => 'Giải thưởng',
        'fa-solid fa-medal' => 'Huy chương',
        'fa-solid fa-trophy' => 'Cúp',
        'fa-solid fa-crown' => 'Vương miện',
        'fa-solid fa-magic' => 'Phép thuật',
        'fa-solid fa-briefcase' => 'Công việc',
        'fa-solid fa-briefcase-medical' => 'Y tế',
        'fa-solid fa-car' => 'Ô tô',
        'fa-solid fa-motorcycle' => 'Xe máy',
        'fa-solid fa-cube' => 'Khối',
        'fa-solid fa-layer-group' => 'Nhóm',
        'fa-solid fa-folder' => 'Thư mục',
        'fa-solid fa-book' => 'Sách',
        'fa-solid fa-utensils' => 'Ăn uống',
        'fa-solid fa-utensils-crossed' => 'Dụng cụ',
        'fa-solid fa-bottle-water' => 'Chai nước',
        'fa-solid fa-gift' => 'Quà tặng',
        'fa-solid fa-shield-halved' => 'Bảo vệ',
        'fa-solid fa-lock' => 'Khóa',
        'fa-solid fa-key' => 'Chìa khóa',
        'fa-solid fa-users' => 'Nhóm người',
        'fa-solid fa-user' => 'Cá nhân',
        'fa-solid fa-screwdriver-wrench' => 'Sửa chữa',
        'fa-solid fa-bug' => 'Sửa lỗi',
        'fa-solid fa-lightbulb' => 'Ý tưởng',
        'fa-solid fa-palette' => 'Màu sắc',
        'fa-solid fa-music' => 'Âm nhạc',
        'fa-solid fa-dumbbell' => 'Thể thao',
        'fa-solid fa-baby' => 'Trẻ em',
        'fa-solid fa-eye' => 'Xem',
    ];
    $currentIsKnown = array_key_exists($current, $icons);
@endphp

<div class="mb-1" data-icon-picker="{{ $name }}">
    <label class="form-label">{{ $label }}</label>
    <div class="input-group">
        <input type="text" name="{{ $name }}"
               class="form-control js-icon-input @error($name) is-invalid @enderror"
               value="{{ $current }}" readonly>
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-chevron-down"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end w-100" style="max-height: 300px; overflow-y: auto;">
            <li>
                <input type="text" class="form-control form-control-sm px-2 py-1 js-icon-search" placeholder="Tìm icon...">
            </li>
            <li><hr class="dropdown-divider"></li>
            @foreach($icons as $class => $labelText)
            <li>
                <a class="dropdown-item d-flex align-items-center gap-2 js-icon-option" href="#"
                   data-icon="{{ $class }}" data-label="{{ $labelText }}">
                    <i class="{{ $class }} fa-lg"></i>
                    <span>{{ $labelText }}</span>
                </a>
            </li>
            @endforeach
        </ul>
    </div>
    @error($name)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    <div class="form-text d-flex align-items-center gap-2">
        <span>Icon đã chọn:</span>
        <i class="fs-5 js-icon-preview {{ $currentIsKnown ? $current : 'd-none' }}"></i>
        <span class="js-icon-preview-text {{ $currentIsKnown ? 'd-none' : '' }}">{{ $current }}</span>
    </div>
</div>

@once
    @push('scripts')
    <script>
        document.addEventListener('click', function (e) {
            const option = e.target.closest('.js-icon-option');
            if (!option) return;
            e.preventDefault();

            const menu = option.closest('.dropdown-menu');
            const wrapper = menu.closest('[data-icon-picker]');
            const iconClass = option.dataset.icon;

            wrapper.querySelector('.js-icon-input').value = iconClass;

            const preview = wrapper.querySelector('.js-icon-preview');
            preview.className = 'fs-5 js-icon-preview ' + iconClass;
            wrapper.querySelector('.js-icon-preview-text').classList.add('d-none');

            const toggle = menu.previousElementSibling;
            const dropdown = bootstrap.Dropdown.getInstance(toggle);
            if (dropdown) dropdown.hide();
        });

        document.addEventListener('input', function (e) {
            if (!e.target.classList.contains('js-icon-search')) return;

            const query = e.target.value.toLowerCase();
            const menu = e.target.closest('.dropdown-menu');

            menu.querySelectorAll('.js-icon-option').forEach(option => {
                const label = option.dataset.label.toLowerCase();
                const iconClass = option.dataset.icon.toLowerCase();
                const matched = label.includes(query) || iconClass.includes(query);
                option.style.display = matched ? '' : 'none';
            });
        });
    </script>
    @endpush
@endonce
