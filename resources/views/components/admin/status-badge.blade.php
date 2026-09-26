@props([
    'status',
    'enum',
    'size' => 'px-3 py-2',
    'pill' => true,
    'icon' => true,
])

{{--
    Badge trạng thái dùng chung cho cột "Trạng thái" trong bảng danh sách
    VÀ cho badge trạng thái ở đầu trang chi tiết.

    $enum  : class enum (App\Enums\*) có labelFor()/badgeClassFor()/iconFor()
    $status: giá trị thô lấy từ model

    Nhãn và màu đều suy ra từ chính enum đó nên luôn khớp với <option> trong
    form chỉnh sửa và dropdown lọc. Lớp viền `border border-<màu>` được tự
    sinh từ badgeClass() để không view nào phải tự thêm tay, tránh tình trạng
    badge có nền màu nhưng mất viền.
--}}
@php
    $badgeClass = $enum::badgeClassFor($status);

    // Ensure border class exists (enums now include border, but keep fallback)
    if (!preg_match('/\bborder\b/', $badgeClass) && preg_match('/\bborder-([a-z0-9-]+)$/', $badgeClass, $matches)) {
        $badgeClass .= ' border border-' . $matches[1];
    }
@endphp

<span {{ $attributes->merge(['class' => 'badge ' . $badgeClass . ' ' . ($pill ? 'rounded-pill ' : '') . $size]) }}>
    @if($icon)<i class="fas fa-{{ $enum::iconFor($status) }} me-1"></i>@endif{{ $enum::labelFor($status) }}
</span>
