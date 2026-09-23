@extends('layouts.app')

@section('title', 'Ghi Nhận Thanh Toán - Giặt Ủi Pro')
@section('page-title', 'Ghi nhận thanh toán')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Thông tin thanh toán</h5>
            <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('payments.store') }}" method="POST">
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Đơn hàng</label>
                    <select class="form-select" name="order_id">
                        <option value="">Chọn đơn hàng</option>
                        @foreach($orders as $order)
                        <option value="{{ $order->id }}">#{{ $order->code }} - {{ $order->customer?->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Số tiền</label>
                    <input type="number" class="form-control" name="amount" placeholder="250000" min="0" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phương thức</label>
                    <select class="form-select" name="method">
                        <option value="cash">Tiền mặt</option>
                        <option value="bank_transfer">Chuyển khoản</option>
                        <option value="e_wallet">Ví điện tử</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Trạng thái</label>
                    <select class="form-select" name="status">
                        <option value="pending">Chưa thanh toán</option>
                        <option value="partial">Một phần</option>
                        <option value="paid">Đã thanh toán</option>
                    </select>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary">Hủy</a>
                <button type="submit" class="btn btn-primary">Lưu thanh toán</button>
            </div>
        </form>
    </div>
</div>
@endsection
