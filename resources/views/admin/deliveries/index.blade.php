@extends('layouts.app')
@section('title', 'Quản lý giao nhận - Sky Laundry')
@section('page-title', 'Quản lý giao nhận')
@section('content')
<div class="order-toolbar mb-4">
    <p class="text-muted mb-0">Theo dõi lịch nhận và giao đồ cho khách hàng.</p>
    <a href="{{ route('deliveries.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Tạo lịch giao nhận</a>
</div>
<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-5">
        <div class="input-group shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3"><i class="fas fa-search text-muted"></i></span>
            <input type="text" name="search" class="form-control border-start-0 py-2 ps-2" placeholder="Tìm theo mã đơn, mã giao nhận, khách hàng..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-md-3">
        <select name="method" class="form-select shadow-sm rounded-3 py-2" onchange="this.form.submit()">
            <option value="">-- Tất cả hình thức --</option>
            <option value="nhan_do" @selected(request('method') === 'nhan_do')>Nhận đồ</option>
            <option value="giao_do" @selected(request('method') === 'giao_do')>Giao đồ</option>
        </select>
    </div>
    <div class="col-12 col-md-3">
        <select name="status" class="form-select shadow-sm rounded-3 py-2" onchange="this.form.submit()">
            <option value="">-- Tất cả trạng thái --</option>
            @foreach($statuses ?? \App\Enums\RecordStatus::options() as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
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
                        <th>Phương thức</th>
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
                        <td><a href="{{ route('orders.show', $delivery->order_id) }}" class="text-decoration-none">{{ $delivery->order?->code ?: '—' }}</a></td>
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
                            @if($delivery->status === 'picking')
                                <span class="badge bg-info-subtle text-info border border-info px-3 py-2 rounded-pill">{{ $delivery->status_label ?? 'Đang nhận đồ' }}</span>
                            @elseif($delivery->status === 'delivering')
                                <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-2 rounded-pill">{{ $delivery->status_label ?? 'Đang giao đồ' }}</span>
                            @elseif($delivery->status === 'completed')
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill">{{ $delivery->status_label ?? 'Hoàn thành' }}</span>
                            @elseif($delivery->status === 'cancelled')
                                <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill">{{ $delivery->status_label ?? 'Đã hủy' }}</span>
                            @else
                                <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-2 rounded-pill">{{ $delivery->status_label ?? 'Chờ xác nhận' }}</span>
                            @endif
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