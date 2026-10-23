@props(['promotion'])

{{--
    Badge "Trạng thái" của chương trình khuyến mãi.

    "Hết hạn" vừa là giá trị lưu trong `TrangThai` vừa là trạng thái suy ra từ
    `NgayKetThuc`, nên nó không nằm trong enum dùng chung RecordStatus. Gom vào
    một chỗ để bộ lọc, bảng danh sách và trang chi tiết không hiển thị lệch nhau.
--}}
@if($promotion->isExpired())
    <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary px-3 py-2 rounded-pill">
        <i class="far fa-hourglass me-1"></i>{{ \App\Models\KhuyenMai::STATUS_EXPIRED }}
    </span>
@else
    <x-admin.status-badge :status="$promotion->TrangThai" :enum="\App\Enums\RecordStatus::class" />
@endif
