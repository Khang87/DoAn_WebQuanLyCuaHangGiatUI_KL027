@props([
    'name' => 'icon',
    'value' => null,
    'label' => 'Biểu tượng',
    'default' => \App\Support\ServiceIcon::DEFAULT,
    'id' => null,
    'options' => null,
])

{{--
    Bộ chọn biểu tượng dùng chung cho các form Tạo / Chỉnh sửa dịch vụ.

    Thứ tự ưu tiên giá trị đang chọn: old() -> $value -> $default.
--}}
@php
    $uid = $id ?: 'icon-picker-' . uniqid();
    $current = old($name, $value) ?: $default;
    $availableIcons = \App\Support\ServiceIcon::options();
    $icons = $options ?? $availableIcons;
    $currentIsKnown = array_key_exists($current, $icons);
    $currentLabel = $icons[$current] ?? 'Chưa chọn biểu tượng';
@endphp

<div class="mb-1" data-icon-picker="{{ $name }}" id="{{ $uid }}">
    <label class="form-label">{{ $label }}</label>
    <div class="input-group">
        <input type="hidden" name="{{ $name }}" class="js-icon-input" value="{{ $current }}">
        <button type="button" class="form-control d-flex align-items-center gap-2 text-start js-icon-toggle @error($name) is-invalid @enderror" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="js-icon-current-preview" @if(! $currentIsKnown) hidden @endif>
                @if($currentIsKnown)
                    @php($currentColors = \App\Support\ServiceIcon::colors($current))
                    <span class="js-icon-option-preview d-inline-flex align-items-center justify-content-center rounded-3" style="width: 40px; height: 40px; color: {{ $currentColors['color'] }}; background-color: {{ $currentColors['background'] }};">
                    <x-admin.service-icon :icon="$current" />
                    </span>
                @endif
            </span>
            <span class="js-icon-label flex-grow-1">{{ $currentLabel }}</span>
            <i class="bi bi-chevron-down"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end w-100" style="max-height: 300px; overflow-y: auto;">
            @foreach($icons as $class => $labelText)
            @php($iconColors = \App\Support\ServiceIcon::colors($class))
            <li>
                <a class="dropdown-item d-flex align-items-center gap-2 js-icon-option" href="#"
                   data-icon="{{ $class }}" data-label="{{ $labelText }}">
                    <span class="js-icon-option-preview d-inline-flex align-items-center justify-content-center rounded-3" style="width: 40px; height: 40px; color: {{ $iconColors['color'] }}; background-color: {{ $iconColors['background'] }};">
                        <x-admin.service-icon :icon="$class" />
                    </span>
                    <span>{{ $labelText }}</span>
                </a>
            </li>
            @endforeach
        </ul>
    </div>
    @error($name)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
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
            wrapper.querySelector('.js-icon-label').textContent = option.dataset.label;

            const preview = wrapper.querySelector('.js-icon-current-preview');
            const selectedPreview = option.querySelector('.js-icon-option-preview');
            preview.replaceChildren(selectedPreview.cloneNode(true));
            preview.hidden = false;

            const toggle = menu.previousElementSibling;
            const dropdown = bootstrap.Dropdown.getInstance(toggle);
            if (dropdown) dropdown.hide();
        });
    </script>
    @endpush
@endonce
