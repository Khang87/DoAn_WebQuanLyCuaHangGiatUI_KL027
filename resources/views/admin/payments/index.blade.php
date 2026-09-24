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

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th>Mã thanh toán</th>
                        <th>Mã đơn</th>
                        <th>Khách hàng</th>
                        <th>Số tiền</th>
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
                        <td>{{ $payment->order?->customer?->name ?: '-' }}</td>
                        <td class="fw-semibold">{{ number_format($payment->amount) }} VNĐ</td>
                        <td>
                            @if($payment->method === 'cash')
                                <i class="bi bi-cash-coin"></i> Tiền mặt
                            @elseif($payment->method === 'bank_transfer')
                                <i class="bi bi-bank"></i> Chuyển khoản
                            @else
                                <i class="bi bi-wallet2"></i> Ví điện tử
                            @endif
                        </td>
                        <td>
                                @if($payment->status === 'paid')
                                    <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Đã thanh toán</span>
                                @elseif($payment->status === 'partial')
                                    <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-2 rounded-pill"><i class="fas fa-hourglass me-1"></i>Một phần</span>
                                @else
                                    <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-x-circle me-1"></i>Chưa thanh toán</span>
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
                    <tr><td colspan="7" class="text-center py-4 text-muted"><i class="fas fa-search fa-2x mb-2 text-secondary d-block"></i>Không tìm thấy dữ liệu phù hợp</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($payments->hasPages())
<div class="mt-3">
    {{ $payments->appends(request()->query())->links('pagination::bootstrap-5') }}
</div>
@endif
@endsection
