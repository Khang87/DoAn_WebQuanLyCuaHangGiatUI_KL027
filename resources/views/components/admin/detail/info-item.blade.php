@props([
    'label',
    'value' => null,
    'muted' => false,
    'icon' => null,
    'class' => '',
])

{{--
    Một cặp nhãn / giá trị theo chuẩn trang chi tiết.
    Nhãn chữ nhỏ xám in hoa, giá trị chữ đậm màu tối.

    Nếu truyền `value` thì component tự hiển thị và tự đổi "—" khi rỗng.
    Muốn giá trị phức tạp (link, badge, bảng...) thì bỏ `value` và viết
    trong slot:
        <x-admin.detail.info-item label="Trạng thái">
            <x-admin.status-badge :status="$model->status" :enum="..." />
        </x-admin.detail.info-item>
--}}
<div {{ $attributes->merge(['class' => 'col ' . $class]) }}>
    <div class="detail-field {{ $muted ? 'detail-field--muted' : '' }}">
        <div class="detail-field__label">
            @if($icon)<i class="{{ $icon }} me-1"></i>@endif{{ $label }}
        </div>
        <div class="detail-field__value">
            @if(! $slot->isEmpty())
                {{ $slot }}
            @elseif($value === null || $value === '')
                <span class="detail-empty-value">—</span>
            @else
                {{ $value }}
            @endif
        </div>
    </div>
</div>
