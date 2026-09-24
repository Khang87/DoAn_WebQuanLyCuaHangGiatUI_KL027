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
        </select>
    </div>
</form>

<div class="card border-0 shadow-sm rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-uppercase text-secondary fs-7 fw-semibold text-start">Mã thanh toán</th>
                        <th class="text-uppercase text-secondary fs-7 fw-semibold text-start">Mã đơn</th>
                        <th class="text-uppercase text-secondary fs-7 fw-semibold text-start">Khách hàng</th>
                        <th class="text-uppercase text-secondary fs-7 fw-semibold text-start">Số tiền</th>
                        <th class="text-uppercase text-secondary fs-7 fw-semibold text-start">Phương thức</th>
                        <th class="text-uppercase text-secondary fs-7 fw-semibold text-start">Trạng thái</th>
                        <th class="text-uppercase text-secondary fs-7 fw-semibold text-start">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                    <tr>
                        <td class="text-start align-middle py-3 fw-bold text-dark">TT{{ $payment->id }}</td>
                        <td class="text-start align-middle py-3">
                            <a href="{{ route('orders.show', $payment->order_id) }}" class="text-decoration-none fw-bold text-dark">{{ $payment->order?->code ?: $payment->order_id }}</a>
                        </td>
                        <td class="text-start align-middle py-3">
                            <div class="fw-bold">{{ $payment->order?->customer?->name ?: '-' }}</div>
                            <small class="text-muted">{{ $payment->order?->customer?->phone ?: 'Chưa có SĐT' }}</small>
                        </td>
                        <td class="text-start align-middle py-3 fw-semibold">{{ number_format($payment->amount) }} VNĐ</td>
                        <td class="text-start align-middle py-3 text-muted">
                            @if($payment->method === 'cash')
                                <i class="bi bi-cash-coin me-1"></i>Tiền mặt
                            @elseif($payment->method === 'bank_transfer')
                                <i class="bi bi-bank me-1"></i>Chuyển khoản
                            @else
                                <i class="bi bi-wallet2 me-1"></i>Ví điện tử
                            @endif
                        </td>
                        <td class="text-start align-middle py-3">
                            @if($payment->status === 'paid')
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2"><i class="fas fa-check-circle me-1"></i>Đã thanh toán</span>
                            @elseif($payment->status === 'partial')
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-2"><i class="fas fa-clock me-1"></i>Một phần</span>
                            @else
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-2"><i class="fas fa-times-circle me-1"></i>Chưa thanh toán</span>
                            @endif
                        </td>
                        <td class="text-start align-middle py-3">
                            <div class="d-flex align-items-center gap-1">
                                <a href="{{ route('payments.show', $payment) }}" class="btn btn-sm btn-outline-info" title="Xem chi tiết"><i class="fas fa-eye"></i></a>
                                <a href="{{ route('payments.edit', $payment) }}" class="btn btn-sm btn-outline-warning" title="Chỉnh sửa"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('payments.destroy', $payment) }}" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa"><i class="fas fa-trash-alt"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="fas fa-search fa-2x mb-2 text-secondary d-block"></i>
                            Không tìm thấy dữ liệu phù hợp
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($payments->hasPages())
<nav class="mt-4">
    <div class="d-flex justify-content-between align-items-center">
        <div class="text-muted small">
            Hiển thị {{ $payments->firstItem() }} - {{ $payments->lastItem() }} của {{ $payments->total() }} thanh toán
        </div>
        {{ $payments->appends(request()->query())->links('pagination::bootstrap-5') }}
    </div>
</nav>
@endif
@endsection
