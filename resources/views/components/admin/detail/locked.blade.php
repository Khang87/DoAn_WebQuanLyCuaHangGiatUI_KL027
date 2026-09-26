@props([
    'text' => 'Bản ghi này đã quyết toán nên không thể sửa hoặc xóa.',
])

{{--
    Thông báo "đã khóa" cho bản ghi đã thanh toán / đã quyết toán.
    Dùng chung một lớp giao diện cho đơn, hóa đơn, thanh toán, chi tiết đơn.
--}}
<div {{ $attributes->merge(['class' => 'detail-lock mb-3']) }}>
    <i class="bi bi-lock-fill"></i>
    <span>{{ $text }}</span>
    @isset($slot){{ $slot }}@endisset
</div>
