@extends('layouts.app')

@section('title', 'Chương trình khuyến mãi - Sky Laundry')
@section('page-title', 'Chương trình khuyến mãi')

@section('content')
<!-- Page Actions: nút "Thêm" luôn nằm góc trên bên trái -->
<div class="page-toolbar">
    <a href="{{ route('promotions.create') }}" class="btn btn-create">
        <i class="bi bi-plus-lg"></i>Thêm chương trình
    </a>
    <p class="text-muted page-toolbar__desc">Các chương trình khuyến mãi đang chạy, phát mã giảm giá cho khách hàng.</p>
</div>
<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-auto flex-grow-1">
        <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control form-control-sm border-start-0 ps-2" placeholder="Tìm theo ID, mã khuyến mãi, tên chương trình..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <x-admin.status-select
            name="status"
            id="filter-status"
            :options="$statuses ?? \App\Enums\RecordStatus::options()"
            placeholder="-- Tất cả trạng thái --"
            class="form-select form-select-sm filter-select shadow-sm rounded-3"
            submit
        />
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <select name="sort" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()">
            <option value="">-- Tất cả cách sắp xếp --</option>
            <option value="latest" @selected(request('sort') === 'latest')>Mới nhất</option>
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
                        <th class="fw-bold text-dark">ID</th>
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
                    @php
                        $isExpired = $promotion->expires_at && $promotion->expires_at->lt(today());
                    @endphp
                    <tr>
                        <td><strong>{{ $promotion->id }}</strong></td>
                        <td><strong>{{ $promotion->name ?: '-' }}</strong></td>
                        <td>{{ $promotion->code ?: '-' }}</td>
                        <td>
                            <div>{{ $promotion->discountSummary() }}</div>
                            <small class="text-muted">{{ $promotion->discountTypeLabel() }}</small>
                        </td>
                        <td>
                            <div>{{ $promotion->usageLabel() }}</div>
                            <small class="text-muted">Hết hạn: {{ $promotion->expires_at?->format('d/m/Y') ?: 'Không hạn' }}</small>
                        </td>
                        <td>
                            @if($isExpired)
                                <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary px-3 py-2 rounded-pill"><i class="far fa-hourglass me-1"></i>Đã hết hạn</span>
                            @else
                                <x-admin.status-badge :status="$promotion->status" :enum="\App\Enums\RecordStatus::class" />
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('promotions.edit', $promotion) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('promotions.destroy', $promotion) }}" method="POST" class="d-inline" id="deletePromotionForm_{{ $promotion->id }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-order-action delete" title="Xóa"><i class="bi bi-trash"></i></button>
                                </form>
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

@if($promotions->hasPages())
<nav class="mt-4">
    <div class="d-flex justify-content-between align-items-center">
        <div class="text-muted small">Hiển thị {{ $promotions->firstItem() }} - {{ $promotions->lastItem() }} của {{ $promotions->total() }} khuyến mãi</div>
        <ul class="pagination mb-0">
            @if ($promotions->onFirstPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $promotions->appends(request()->query())->url($promotions->currentPage() - 1) }}"><i class="bi bi-chevron-left"></i></a></li>
            @endif
            @foreach ($promotions->getUrlRange(max(1, $promotions->currentPage() - 2), min($promotions->lastPage(), $promotions->currentPage() + 2)) as $page => $url)
                @if ($page == $promotions->currentPage())
                    <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $promotions->appends(request()->query())->url($page) }}">{{ $page }}</a></li>
                @endif
            @endforeach
            @if ($promotions->onLastPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $promotions->appends(request()->query())->url($promotions->currentPage() + 1) }}"><i class="bi bi-chevron-right"></i></a></li>
            @endif
        </ul>
    </div>
</nav>
@endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[id^="deletePromotionForm_"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                if (typeof Swal === 'undefined') {
                    if (confirm('Xóa?')) {
                        form.submit();
                    }
                    return;
                }

                Swal.fire({
                    title: 'Xóa khuyến mãi?',
                    text: 'Hành động này không thể hoàn tác.',
                    icon: 'warning',
                    showCancelButton: true,
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
    });
</script>
@endpush