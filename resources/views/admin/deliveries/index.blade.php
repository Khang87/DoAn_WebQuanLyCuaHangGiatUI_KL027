@extends('layouts.app')
@section('title', 'Quản lý giao nhận - Sky Laundry')
@section('page-title', 'Quản lý giao nhận')
@section('content')
<!-- Page Actions: nút "Thêm" luôn nằm góc trên bên trái -->
<div class="page-toolbar">
    <a href="{{ route('deliveries.create') }}" class="btn btn-create">
        <i class="bi bi-plus-lg"></i>Thêm lịch giao nhận
    </a>
    <p class="text-muted page-toolbar__desc">Theo dõi lịch nhận và giao đồ cho khách hàng.</p>
</div>
<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-auto flex-grow-1">
        <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3"><i class="fas fa-search text-muted"></i></span>
            <input type="text" name="search" class="form-control form-control-sm border-start-0 ps-2" placeholder="Tìm theo mã đơn, mã giao nhận, khách hàng..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <select name="method" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()">
            <option value="">-- Tất cả hình thức --</option>
            <option value="nhan_do" @selected(request('method') === 'nhan_do')>Nhận đồ</option>
            <option value="giao_do" @selected(request('method') === 'giao_do')>Giao đồ</option>
        </select>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <x-admin.status-select
            name="status"
            id="filter-status"
            :options="\App\Enums\DeliveryStatus::options()"
            placeholder="-- Tất cả trạng thái --"
            class="form-select form-select-sm filter-select shadow-sm rounded-3"
            submit
        />
    </div>
</form>
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle table-custom">
                <thead class="table-light">
                    <tr>
                        <th>STT</th>
                        <th>Mã giao nhận</th>
                        <th>Mã đơn hàng</th>
                        <th>Khách hàng</th>
                        <th>Hình thức</th>
                        <th>Nhân viên</th>
                        <th>Thời gian</th>
                        <th>Trạng thái</th>
                        <th class="text-center">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deliveries as $delivery)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td><strong>{{ $delivery->code ?: ('GH' . str_pad($delivery->id, 3, '0', STR_PAD_LEFT)) }}</strong></td>
                        <td><span class="text-dark">{{ $delivery->order?->code ?: '—' }}</span></td>
                        <td>{{ $delivery->customer?->name ?: $delivery->order?->customer?->name ?: '—' }}</td>
                        <td>
                            @if($delivery->method === 'nhan_do')
                                <i class="bi bi-box-arrow-in-down me-1"></i>Nhận đồ
                            @else
                                <i class="bi bi-truck me-1"></i>Giao đồ
                            @endif
                        </td>
                        <td>{{ $delivery->employee?->name ?: $delivery->employee_name ?: 'Chưa phân công' }}</td>
                        <td>
                            {{ $delivery->pickup_date?->format('d/m/Y') ?: '—' }}
                            <br><small class="text-muted">{{ $delivery->pickup_time?->format('H:i') ?: '—' }}</small>
                        </td>
                        <td>
                            <x-admin.status-badge :status="$delivery->status" :enum="\App\Enums\DeliveryStatus::class" />
                        </td>
                        <td class="text-center">
                            <div class="d-flex gap-2 justify-content-center">
                                <a href="{{ route('deliveries.show', $delivery) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('deliveries.edit', $delivery) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('deliveries.destroy', $delivery) }}" method="POST" class="d-inline" id="deleteDeliveryForm_{{ $delivery->id }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-order-action delete" title="Xóa"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">Chưa có dữ liệu giao nhận</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($deliveries->hasPages())
<nav class="mt-4">
    <div class="d-flex justify-content-between align-items-center">
        <div class="text-muted small">Hiển thị {{ $deliveries->firstItem() }} - {{ $deliveries->lastItem() }} của {{ $deliveries->total() }} giao nhận</div>
        <ul class="pagination mb-0">
            @if ($deliveries->onFirstPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $deliveries->appends(request()->query())->url($deliveries->currentPage() - 1) }}"><i class="bi bi-chevron-left"></i></a></li>
            @endif
            @foreach ($deliveries->getUrlRange(max(1, $deliveries->currentPage() - 2), min($deliveries->lastPage(), $deliveries->currentPage() + 2)) as $page => $url)
                @if ($page == $deliveries->currentPage())
                    <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $deliveries->appends(request()->query())->url($page) }}">{{ $page }}</a></li>
                @endif
            @endforeach
            @if ($deliveries->onLastPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $deliveries->appends(request()->query())->url($deliveries->currentPage() + 1) }}"><i class="bi bi-chevron-right"></i></a></li>
            @endif
        </ul>
    </div>
</nav>
@endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[id^="deleteDeliveryForm_"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                if (typeof Swal === 'undefined') {
                    if (confirm('Bạn có chắc muốn xóa?')) {
                        form.submit();
                    }
                    return;
                }

                Swal.fire({
                    title: 'Xóa lịch giao nhận?',
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