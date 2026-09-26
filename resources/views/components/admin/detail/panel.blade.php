@props([
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'iconClass' => 'bg-primary-subtle text-primary',
    'flush' => false,
    'class' => '',
])

{{--
    Thẻ nội dung chuẩn dùng trong cả cột chính và cột phụ của trang chi tiết.

    :flush    bỏ padding card-body (dùng khi bên trong là bảng)
    :icon     biểu tượng tròn bên trái tiêu đề thẻ
    :iconClass màu nền/màu chữ của biểu tượng (phải truyền dạng bound
               `:iconClass="'...'"`, Blade không tự đổi tên prop nhiều từ)
    slot `header` : nội dung phụ căn phải trên header (nút nhỏ, badge...)

    Cách dùng:
        <x-admin.detail.panel title="Thông tin đơn hàng" icon="bi-receipt">
            ...
            <x-slot:header>
                <a href="..." class="btn btn-sm btn-outline-secondary">Xuất</a>
            </x-slot:header>
        </x-admin.detail.panel>
--}}
<div {{ $attributes->merge(['class' => 'card detail-panel shadow-sm mb-4 ' . $class]) }}>
    @if($title || isset($header))
        <div class="card-header detail-panel__header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-3">
                @if($icon)
                    <span class="detail-panel__icon {{ $iconClass }}"><i class="{{ $icon }}"></i></span>
                @endif
                <div class="flex-grow-1">
                    <h5 class="detail-panel__title">{{ $title }}</h5>
                    @if($subtitle)
                        <div class="detail-header__subtitle">{{ $subtitle }}</div>
                    @endif
                </div>
            </div>
            @isset($header){{ $header }}@endisset
        </div>
    @endif

    <div class="{{ $flush ? 'p-0' : 'card-body detail-panel__body' }}">
        {{ $slot }}
    </div>

    @isset($footer){{ $footer }}@endisset
</div>
