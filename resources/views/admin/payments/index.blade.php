@extends('layouts.app')
@section('title', 'Thanh Toán - Giặt Ủi Pro')
@section('page-title', 'Thanh Toán')

@section('content')
<div class="order-toolbar">
    <p class="text-muted mb-0">Theo dõi các khoản thu của đơn hàng.</p>
    <a href="{{ route('payments.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-2"></i>Ghi nhận thanh toán
    </a>
</div>

<form action="{{ route('payments.index') }}" method="GET" class="d-flex gap-2 mb-3">
    <div class="input-group" style="width: 200px;">
        <input type="text" name="search" class="form-control" placeholder="Tìm kiếm..." value="{{ request('search') }}">
    </div>
    <select name="method" class="form-select" style="width: auto;" onchange="this.form.submit()">
        <option value="">Tất cả phương thức</option>
        @foreach($methods as $value => $label)
            <option value="{{ $value }}" @selected(request('method') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <select name="status" class="form-select" style="width: auto;" onchange="this.form.submit()">
        <option value="">Tất cả trạng thái</option>
        <option value="pending" @selected(request('status') === 'pending')>Chờ thanh toán</option>
        <option value="partial" @selected(request('status') === 'partial')>Một phần</option>
        <option value="paid" @selected(request('status') === 'paid')>Đã thanh toán</option>
    </select>
    @if(request('method'))
        <input type="hidden" name="method" value="{{ request('method') }}">
    @endif
    @if(request('status'))
        <input type="hidden" name="status" value="{{ request('status') }}">
    @endif
    <button type="submit" class="btn btn-outline-secondary">Lọc</button>
    <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary">Xóa</a>
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
                        <td><strong>#TT{{ $payment->id }}</strong></td>
                        <td><a href="{{ route('orders.show', $payment->order_id) }}">#{{ $payment->order?->code ?: $payment->order_id }}</a></td>
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
                                <span class="badge-status badge-completed">Đã thanh toán</span>
                            @elseif($payment->status === 'partial')
                                <span class="badge-status badge-warning">Một phần</span>
                            @else
                                <span class="badge-status badge-pending">Chưa thanh toán</span>
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
                    <tr><td colspan="7" class="text-center text-muted py-4">Chưa có thanh toán nào</td></tr>
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
