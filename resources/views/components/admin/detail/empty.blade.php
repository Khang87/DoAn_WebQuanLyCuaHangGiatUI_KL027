@props([
    'message' => 'Chưa có dữ liệu',
    'icon' => 'bi-inbox',
])

{{--
    Trạng thái rỗng dùng chung cho khối nội dung không có dữ liệu.
    Với bảng, viết thẳng <td colspan="N" class="detail-empty">...</td>.
--}}
<div {{ $attributes->merge(['class' => 'detail-empty']) }}>
    <i class="{{ $icon }}"></i>{{ $message }}
</div>
