@props([
    'columns' => 2,
    'class' => '',
])

{{--
    Lưới hiển thị cặp nhãn / giá trị của trang chi tiết.
    :columns = số cột trên màn hình >= md (1..4), các ô tự chia đều nhau.

    <x-admin.detail.info-grid :columns="2">
        <x-admin.detail.info-item label="Mã khách hàng" :value="$customer->code" />
    </x-admin.detail.info-grid>
--}}
<div {{ $attributes->merge(['class' => 'row g-3 row-cols-1 row-cols-md-' . $columns . ' ' . $class]) }}>
    {{ $slot }}
</div>
