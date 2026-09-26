@extends('layouts.app')
@section('title', 'Bảng giá - Sky Laundry')
@section('page-title', 'Bảng giá')
@section('content')
<!-- Page Actions: nút "Thêm" luôn nằm góc trên bên trái -->
<div class="page-toolbar">
    <a href="{{ route('pricings.create') }}" class="btn btn-create">
        <i class="bi bi-plus-lg"></i>Thêm bảng giá
    </a>
    <p class="text-muted page-toolbar__desc">Giá dịch vụ theo loại đồ và đơn vị tính.</p>
</div>

<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-auto flex-grow-1">
        <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3"><i class="fas fa-search text-muted"></i></span>
            <input type="text" name="search" class="form-control form-control-sm border-start-0 ps-2" placeholder="Tìm theo mã, tên dịch vụ..." value="{{ request('search') }}">
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
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Mã bảng giá</th>
                        <th>Dịch vụ</th>
                        <th>Loại đồ giặt</th>
                        <th>Đơn vị</th>
                        <th>Đơn giá</th>
                        <th>Ngày áp dụng</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pricings as $pricing)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $pricing->code ?? 'PG' . str_pad($pricing->id, 4, '0', STR_PAD_LEFT) }}</td>
                        <td><strong>{{ $pricing->service?->name ?: $pricing->name ?: '—' }}</strong></td>
                        <td>{{ $pricing->garment?->name ?: $pricing->garment_name ?: '—' }}</td>
                        <td>{{ $pricing->unit ?: 'kg' }}</td>
                                <td class="fw-semibold text-dark">{{ number_format($pricing->price) }} VNĐ</td>
                        <td>{{ $pricing->effective_date?->format('d/m/Y') ?: $pricing->created_at?->format('d/m/Y') ?: '—' }}</td>
                        <td>
                            <x-admin.status-badge :status="$pricing->status" :enum="\App\Enums\RecordStatus::class" />
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('pricings.show', $pricing) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('pricings.edit', $pricing) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('pricings.destroy', $pricing) }}" method="POST" class="d-inline" id="deletePricingForm_{{ $pricing->id }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-order-action delete" title="Xóa"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">Chưa có bảng giá nào</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($pricings->hasPages())
<nav class="mt-4">
    <div class="d-flex justify-content-between align-items-center">
        <div class="text-muted small">Hiển thị {{ $pricings->firstItem() }} - {{ $pricings->lastItem() }} của {{ $pricings->total() }} bảng giá</div>
        <ul class="pagination mb-0">
            @if ($pricings->onFirstPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $pricings->appends(request()->query())->url($pricings->currentPage() - 1) }}"><i class="bi bi-chevron-left"></i></a></li>
            @endif
            @foreach ($pricings->getUrlRange(max(1, $pricings->currentPage() - 2), min($pricings->lastPage(), $pricings->currentPage() + 2)) as $page => $url)
                @if ($page == $pricings->currentPage())
                    <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $pricings->appends(request()->query())->url($page) }}">{{ $page }}</a></li>
                @endif
            @endforeach
            @if ($pricings->onLastPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $pricings->appends(request()->query())->url($pricings->currentPage() + 1) }}"><i class="bi bi-chevron-right"></i></a></li>
            @endif
        </ul>
    </div>
</nav>
@endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[id^="deletePricingForm_"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                if (typeof Swal === 'undefined') {
                    if (confirm('Bạn có chắc muốn xóa?')) {
                        form.submit();
                    }
                    return;
                }

                Swal.fire({
                    title: 'Xóa bảng giá?',
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