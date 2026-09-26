@props([
    'value' => 0,
    'negative' => false,
    'class' => '',
    'unit' => 'VNĐ',
])

{{--
    Số tiền theo đúng chuẩn tiền tệ Việt Nam: 1.500.000 VNĐ
    Dùng ở MỌI vị trí hiển thị tiền trong trang chi tiết (và bảng con)
    để không còn trường hợp ghi "VND", thiếu dấu phân cách hoặc lệch
    khoảng trắng trước "VNĐ".

    :negative=true  -> tiền giảm, tự thêm dấu "-" và tô đỏ.
--}}
<span {{ $attributes->merge(['class' => 'detail-money ' . $class]) }}>{{ $negative ? '-' : '' }}{{ number_format((float) $value) }} {{ $unit }}</span>
