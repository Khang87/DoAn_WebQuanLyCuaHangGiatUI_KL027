@extends('layouts.app')

@section('title', 'Chi tiet khach hang - Sky Laundry')
@section('page-title', 'Chi tiet khach hang')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Quay lai
    </a>
    <a href="{{ route('customers.edit', $customer->id) }}" class="btn btn-primary btn-sm">
        <i class="bi bi-pencil me-1"></i>Chinh sua
    </a>
</div>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Thong tin khach hang</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr><td><strong>Ma</strong></td><td>{{ $customer->code }}</td></tr>
                    <tr><td><strong>Ho ten</strong></td><td>{{ $customer->name }}</td></tr>
                    <tr><td><strong>Email</strong></td><td>{{ $customer->email ?: '-' }}</td></tr>
                    <tr><td><strong>So dien thoai</strong></td><td>{{ $customer->phone ?: '-' }}</td></tr>
                    <tr><td><strong>Diem tich luy</strong></td><td><strong>{{ number_format($customer->points) }}</strong> diem</td></tr>
                    <tr><td><strong>Ngay dang ky</strong></td><td>{{ $customer->created_at?->format('d/m/Y H:i') }}</td></tr>
                    <tr><td><strong>Trang thai</strong></td>
                        <td>
                            @if($customer->deleted_at)
                                <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-trash me-1"></i>Da xoa</span>
                            @else
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>Hoat dong</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr><td><strong>Tong chi tieu</strong></td><td><strong>{{ number_format($totalSpent) }} VND</strong></td></tr>
                    <tr><td><strong>Tong don hang</strong></td><td>{{ $orderCount }}</td></tr>
                </table>
            </div>
        </div>
        @if($customer->address)
        <div class="mt-3">
            <strong>Dia chi:</strong> {{ $customer->address }}
        </div>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Lich su don hang</h5>
    </div>
    <div class="card-body p-0">
        @if($orders->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Ma don</th><th>Dich vu</th><th>Tong tien</th><th>Trang thai</th><th>Ngay tao</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orders as $order)
                    <tr>
                        <td><strong>{{ $order->code }}</strong></td>
                        <td>{{ $order->service?->name ?: '-' }}</td>
                        <td>{{ number_format($order->total_amount) }} VND</td>
                        @if($order->status === 'completed')
                            <span class="badge bg-success-subtle text-success border border-success px-2 py-1 rounded-pill"><i class="fas fa-check-circle"></i></span>
                        @elseif($order->status === 'cancelled')
                            <span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1 rounded-pill"><i class="fas fa-x-circle"></i></span>
                        @elseif($order->status === 'pending')
                            <span class="badge bg-warning-subtle text-warning border border-warning px-2 py-1 rounded-pill"><i class="fas fa-hourglass"></i></span>
                        @elseif($order->status === 'processing')
                            <span class="badge bg-info-subtle text-info border border-info px-2 py-1 rounded-pill"><i class="fas fa-cog"></i></span>
                        @elseif($order->status === 'delivering')
                            <span class="badge bg-primary-subtle text-primary border border-primary px-2 py-1 rounded-pill"><i class="fas fa-truck"></i></span>
                        @elseif($order->status === 'washing')
                            <span class="badge bg-warning-subtle text-warning border border-warning px-2 py-1 rounded-pill"><i class="fas fa-washer"></i></span>
                        @elseif($order->status === 'washed')
                            <span class="badge bg-info-subtle text-info border border-info px-2 py-1 rounded-pill"><i class="fas fa-tshirt-pocket"></i></span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary px-2 py-1 rounded-pill">{{ $order->status }}</span>
                        @endif
                        <td>{{ $order->created_at?->format('d/m/Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $orders->links() }}
        @else
        <p class="text-center text-muted py-4">Chua co don hang</p>
        @endif
    </div>
</div>
@endsection