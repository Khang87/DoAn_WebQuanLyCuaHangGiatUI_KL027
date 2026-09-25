@extends('layouts.app')
@section('title', 'Thanh Toán - Sky Laundry')
@section('page-title', 'Thanh Toán')

@section('content')
<div class="order-toolbar d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0">Theo dõi các khoản thu của đơn hàng.</p>
    <a href="{{ route('payments.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-2"></i>Ghi nhận thanh toán
    </a>
</div>

<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-5">
        <div class="input-group shadow-sm rounded-3 overflow-hidden">
            <span class="input-group-text bg-white border-end-0 ps-3">
                <i class="fas fa-search text-muted"></i>
            </span>
            <input type="text" name="search" class="form-control border-start-0 py-2 ps-2" placeholder="Tìm theo ID, mã thanh toán, mã hóa đơn, tên khách..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-md-3">
        <select name="method" class="form-select shadow-sm rounded-3 py-2" style="min-width: 220px;" onchange="this.form.submit()">
            <option value="">-- Tất cả phương thức --</option>
            @foreach($methods as $value => $label)
                <option value="{{ $value }}" @selected(request('method') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-md-3">
        <select name="status" class="form-select shadow-sm rounded-3 py-2" style="min-width: 200px;" onchange="this.form.submit()">
            <option value="">-- Tất cả trạng thái --</option>
            <option value="pending" @selected(request('status') === 'pending')>Chờ thanh toán</option>
            <option value="partial" @selected(request('status') === 'partial')>Một phần</option>
            <option value="paid" @selected(request('status') === 'paid')>Đã thanh toán</option>
            <option value="failed" @selected(request('status') === 'failed')>Thất bại</option>
            <option value="refunded" @selected(request('status') === 'refunded')>Đã hoàn tiền</option>
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
                        @php
                            $currentSortBy = request('sort_by');
                            $currentSortOrder = request('sort_order', 'desc');
                            $nextOrderId = ($currentSortBy === 'id' && $currentSortOrder === 'asc') ? 'desc' : 'asc';
                            $nextOrderAmount = ($currentSortBy === 'amount' && $currentSortOrder === 'asc') ? 'desc' : 'asc';
                        @endphp
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'id', 'sort_order' => $nextOrderId]) }}" class="text-dark text-decoration-none">
                                Mã thanh toán
                                @if($currentSortBy === 'id') @if($currentSortOrder === 'asc') <i class="bi bi-sort-up"></i> @else <i class="bi bi-sort-down"></i> @endif @endif
                            </a>
                        </th>
                        <th>Mã đơn</th>
                        <th>Khách hàng</th>
                        <th>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'amount', 'sort_order' => $nextOrderAmount]) }}" class="text-dark text-decoration-none">
                                Số tiền
                                @if($currentSortBy === 'amount') @if($currentSortOrder === 'asc') <i class="bi bi-sort-up"></i> @else <i class="bi bi-sort-down"></i> @endif @endif
                            </a>
                        </th>
                        <th>Phương thức</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                    <tr>
                        <td><strong>TT{{ $payment->id }}</strong></td>
                        <td><a href="{{ route('orders.show', $payment->order_id) }}">{{ $payment->order?->code ?: $payment->order_id }}</a></td>
                        <td>
                <div class="d-flex align-items-center">
                                 @php
                                     $avatarId = $payment->order?->customer?->id ?? ($payment->order?->id ?? $payment->id);
                                     $avatarUrl = 'assets/images/user_' . (($avatarId % 8) + 1) . '.jpg';
                                 @endphp
                                 <img src="{{ asset($avatarUrl) }}" alt="Ảnh khách hàng" class="rounded-circle me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                 <div>
                                    <div class="fw-semibold">{{ $payment->order?->customer?->name ?: '-' }}</div>
                                    <small class="text-muted">{{ $payment->order?->customer?->phone ?: 'Chưa có SĐT' }}</small>
                                </div>
                            </div>
                        </td>
                        <td class="fw-semibold">{{ number_format($payment->amount) }} VNĐ</td>
                        <td>
                            @if($payment->method === 'cash')
                                <i class="bi bi-cash-coin me-1"></i>Tiền mặt
                            @elseif($payment->method === 'bank_transfer')
                                <i class="bi bi-bank me-1"></i>Chuyển khoản (QR)
                            @elseif($payment->method === 'momo')
                                <i class="bi bi-phone me-1"></i>Ví MoMo
                            @elseif($payment->method === 'credit_card')
                                <i class="bi bi-credit-card me-1"></i>Thẻ ATM/Credit
                            @else
                                <i class="bi bi-wallet2 me-1"></i>Ví điện tử
                            @endif
                        </td>
                        <td>
                            @if($payment->status === 'paid')
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Đã thanh toán</span>
                            @elseif($payment->status === 'partial')
                                <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-2 rounded-pill"><i class="fas fa-clock me-1"></i>Một phần</span>
                            @elseif($payment->status === 'failed')
                                <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-times-circle me-1"></i>Thất bại</span>
                            @elseif($payment->status === 'refunded')
                                <span class="badge bg-info-subtle text-info border border-info px-3 py-2 rounded-pill"><i class="fas fa-undo me-1"></i>Đã hoàn tiền</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary px-3 py-2 rounded-pill"><i class="fas fa-hourglass me-1"></i>Chưa thanh toán</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('payments.show', $payment) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('payments.edit', $payment) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('payments.destroy', $payment) }}" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa?')">
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
