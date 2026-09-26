@extends('layouts.app')
@section('title', 'Thanh toán - Sky Laundry')
@section('page-title', 'Thanh toán')

@section('content')
<!-- Page Actions: nút "Thêm" luôn nằm góc trên bên trái -->
<div class="page-toolbar">
    <a href="{{ route('payments.create') }}" class="btn btn-create">
        <i class="bi bi-plus-lg"></i>Thêm thanh toán
    </a>
    <p class="text-muted page-toolbar__desc">Theo dõi các khoản thu của đơn hàng.</p>
</div>

<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-auto flex-grow-1">
        <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control form-control-sm border-start-0 ps-2" placeholder="Tìm theo ID, mã thanh toán, tên khách..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <select name="method" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()">
            <option value="">-- Tất cả phương thức --</option>
            @foreach($methods as $value => $label)
                <option value="{{ $value }}" @selected(request('method') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <x-admin.status-select
            name="status"
            id="filter-status"
            :options="\App\Enums\PaymentStatus::options()"
            placeholder="-- Tất cả trạng thái --"
            class="form-select form-select-sm filter-select shadow-sm rounded-3"
            submit
        />
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <select name="sort" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()">
            <option value="">-- Tất cả cách sắp xếp --</option>
            <option value="created_at_desc" @selected(request('sort') === 'created_at_desc')>Mới nhất</option>
            <option value="created_at_asc" @selected(request('sort') === 'created_at_asc')>Cũ nhất</option>
            <option value="amount_desc" @selected(request('sort') === 'amount_desc')>Số tiền cao nhất</option>
            <option value="amount_asc" @selected(request('sort') === 'amount_asc')>Số tiền thấp nhất</option>
            <option value="id_desc" @selected(request('sort') === 'id_desc')>Mã giảm dần</option>
            <option value="id_asc" @selected(request('sort') === 'id_asc')>Mã tăng dần</option>
        </select>
    </div>
</form>

<!-- Payments Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th class="fw-semibold text-dark">Mã thanh toán</th>
                        <th class="fw-semibold text-dark">Mã đơn hàng</th>
                        <th class="fw-semibold text-dark">Khách hàng</th>
                        <th class="fw-semibold text-dark">Số tiền</th>
                        <th class="fw-semibold text-dark">Phương thức</th>
                        <th class="fw-semibold text-dark">Trạng thái</th>
                        <th class="fw-semibold text-dark">Ngày thanh toán</th>
                        <th class="fw-semibold text-dark">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                    <tr>
                        <td><strong>TT{{ $payment->id }}</strong></td>
                        <td><span class="text-dark">{{ $payment->order?->code ?: $payment->order_id }}</span></td>
                        <td>
                            <div class="d-flex align-items-center">
                                @php
                                    $avatarUrl = $payment->order?->customer?->avatar_url ?? asset('assets/images/user_1.jpg');
                                @endphp
                                <img src="{{ asset($avatarUrl) }}" alt="Ảnh khách hàng" class="rounded-circle me-2 avatar-cover" style="width: 40px; height: 40px;">
                                <div>
                                    <div class="fw-semibold">{{ $payment->order?->customer?->name ?: '-' }}</div>
                                    <small class="text-muted">{{ $payment->order?->customer?->phone ?: 'Chưa có SĐT' }}</small>
                                </div>
                            </div>
                        </td>
                        <td class="fw-semibold">{{ number_format($payment->amount) }} VNĐ</td>
                        <td>
                            <i class="bi {{ $payment->getMethodIcon() }} me-1"></i>{{ $payment->getMethodLabel() }}
                        </td>
                        <td>
                            <x-admin.status-badge :status="$payment->status" :enum="\App\Enums\PaymentStatus::class" />
                        </td>
                        <td>{{ $payment->paid_at?->format('d/m/Y H:i') ?? $payment->created_at?->format('d/m/Y H:i') }}</td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('payments.show', $payment) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                @if($payment->can_edit)
                                    <a href="{{ route('payments.edit', $payment) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                @endif
                                @if($payment->can_delete)
                                    <form action="{{ route('payments.destroy', $payment) }}" method="POST" class="d-inline" id="deletePaymentForm_{{ $payment->id }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-order-action delete" title="Xóa"><i class="bi bi-trash"></i></button>
                                    </form>
                                @else
                                    <span class="badge bg-success-subtle text-success-emphasis border border-success px-3 py-2 rounded-pill" title="Đã thanh toán nên không thể sửa hoặc xóa">
                                        <i class="bi bi-lock me-1"></i>Đã thanh toán
                                    </span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Chưa có dữ liệu nào</td>
                    </tr>
                    @endempty
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($payments->hasPages())
<nav class="mt-4">
    <div class="d-flex justify-content-between align-items-center">
        <div class="text-muted small">Hiển thị {{ $payments->firstItem() }} - {{ $payments->lastItem() }} của {{ $payments->total() }} thanh toán</div>
        <ul class="pagination mb-0">
            @if ($payments->onFirstPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $payments->appends(request()->query())->url($payments->currentPage() - 1) }}"><i class="bi bi-chevron-left"></i></a></li>
            @endif
            @foreach ($payments->getUrlRange(max(1, $payments->currentPage() - 2), min($payments->lastPage(), $payments->currentPage() + 2)) as $page => $url)
                @if ($page == $payments->currentPage())
                    <li class="page-item active"><span class="page-link">{{ $page }}</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $payments->appends(request()->query())->url($page) }}">{{ $page }}</a></li>
                @endif
            @endforeach
            @if ($payments->onLastPage())
                <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right"></i></span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $payments->appends(request()->query())->url($payments->currentPage() + 1) }}"><i class="bi bi-chevron-right"></i></a></li>
            @endif
        </ul>
    </div>
</nav>
@endif
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[id^="deletePaymentForm_"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                if (typeof Swal === 'undefined') {
                    if (confirm('Bạn có chắc muốn xóa?')) {
                        form.submit();
                    }
                    return;
                }

                Swal.fire({
                    title: 'Xóa thanh toán?',
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
