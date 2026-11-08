@extends('layouts.app')

@section('title', 'Chương trình khuyến mãi - Sky Laundry')
@section('page-title', 'Chương trình khuyến mãi')

@section('content')
<!-- Page Actions: nút "Thêm" luôn nằm góc trên bên trái -->
<div class="page-toolbar">
    @can('promotions.create')
        <a href="{{ route('promotions.create') }}" class="btn btn-create">
            <i class="bi bi-plus-lg"></i>Thêm chương trình
        </a>
    @endcan
    <p class="text-muted page-toolbar__desc">Các chương trình khuyến mãi đang chạy, phát mã giảm giá cho khách hàng.</p>
</div>
<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-auto flex-grow-1">
        <x-admin.search-field placeholder="Tìm theo mã khuyến mãi, tên chương trình..." label="Tìm kiếm khuyến mãi" />
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <x-admin.status-select
            name="status"
            id="filter-status"
            :options="$statuses ?? \App\Models\KhuyenMai::statusOptions()"
            placeholder="Tất cả trạng thái"
            class="form-select form-select-sm filter-select shadow-sm rounded-3"
            submit
        />
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <select name="sort" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()">
            <option value="latest" @selected(request('sort', 'latest') === 'latest')>Mới nhất</option>
            <option value="oldest" @selected(request('sort') === 'oldest')>Cũ nhất</option>
            <option value="name_asc" @selected(request('sort') === 'name_asc')>Tên A-Z</option>
            <option value="name_desc" @selected(request('sort') === 'name_desc')>Tên Z-A</option>
            <option value="code_asc" @selected(request('sort') === 'code_asc')>Mã tăng dần</option>
            <option value="code_desc" @selected(request('sort') === 'code_desc')>Mã giảm dần</option>
            <option value="discount_value_desc" @selected(request('sort') === 'discount_value_desc')>Giá trị cao nhất</option>
            <option value="discount_value_asc" @selected(request('sort') === 'discount_value_asc')>Giá trị thấp nhất</option>
            <option value="expires_at_asc" @selected(request('sort') === 'expires_at_asc')>Hạn sử dụng sớm nhất</option>
        </select>
    </div>
</form>

<!-- Promotions Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th scope="col">STT</th>
                        <th class="fw-bold text-dark">Tên chương trình</th>
                        <th class="fw-bold text-dark">Mã khuyến mãi</th>
                        <th class="fw-bold text-dark">Giá trị giảm</th>
                        <th class="fw-bold text-dark">Hạn sử dụng</th>
                        <th class="fw-bold text-dark">Trạng thái</th>
                        <th class="fw-bold text-dark">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($promotions as $promotion)
                    <tr>
                        <td>{{ $promotions->firstItem() + $loop->index }}</td>
                        <td><strong>{{ $promotion->TenKhuyenMai ?: '-' }}</strong></td>
                        <td>{{ $promotion->MaKhuyenMai ?: '-' }}</td>
                        <td>
                            <div>{{ $promotion->discountSummary() }}</div>
                            <small class="text-muted">{{ $promotion->discountTypeLabel() }}</small>
                        </td>
                        <td>
                            {{ $promotion->NgayKetThuc?->format('d/m/Y') ?: 'Không hạn' }}
                        </td>
                        <td>
                            <x-admin.promotion-status-badge :promotion="$promotion" />
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                @can('promotions.view')
                                    <a href="{{ route('promotions.show', $promotion) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                @endcan
                                @can('promotions.edit')
                                    <a href="{{ route('promotions.edit', $promotion) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                @endcan
                                @can('promotions.delete')
                                    <form action="{{ route('promotions.destroy', $promotion->KhuyenMaiID) }}" method="POST" class="d-inline" id="deletePromotionForm_{{ $promotion->KhuyenMaiID }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-order-action delete" title="Xóa" data-promotion-active="{{ $promotion->TrangThai === 'Hoạt động' ? 'true' : 'false' }}"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                     </tr>
                     @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Chưa có dữ liệu nào</td>
                    </tr>
                    @endempty
                </tbody>
            </table>
        </div>
    </div>
</div>

<x-admin.pagination :paginator="$promotions" label="khuyến mãi" />
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.addEventListener('submit', function(e) {
            const form = e.target.closest('[id^="deletePromotionForm_"]');
            if (!form) {
                return;
            }

            e.preventDefault();
            const isActive = form.querySelector('[data-promotion-active]').dataset.promotionActive === 'true';
            const confirmationText = isActive
                ? 'Khuyến mãi có trạng thái Hoạt động sẽ được chuyển sang Tạm ngưng, không bị xóa cứng.'
                : 'Khuyến mãi sẽ bị xóa vĩnh viễn. Liên kết trên đơn hàng/Booking sẽ được gỡ; số tiền giảm đã lưu trên đơn hàng được giữ nguyên.';

            if (typeof Swal === 'undefined') {
                if (confirm(confirmationText)) {
                    form.submit();
                }
                return;
            }

            Swal.fire({
                title: 'Xóa khuyến mãi?',
                text: confirmationText,
                icon: 'warning',
                showCancelButton: true,
                reverseButtons: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Xóa',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
</script>
@endpush
