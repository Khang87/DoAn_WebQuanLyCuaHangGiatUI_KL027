@props([
    'name' => 'search',
    'placeholder' => 'Tìm kiếm...',
    'label' => 'Tìm kiếm',
    'value' => null,
])

<div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden filter-search">
    <input
        type="search"
        name="{{ $name }}"
        class="form-control form-control-sm border-end-0 ps-3"
        placeholder="{{ $placeholder }}"
        value="{{ $value ?? request($name) }}"
        aria-label="{{ $label }}"
        data-auto-search-input
    >
    <button type="submit" class="input-group-text bg-white border-start-0 pe-3" aria-label="{{ $label }}" title="{{ $label }}">
        <i class="fas fa-search text-muted" aria-hidden="true"></i>
    </button>
</div>
