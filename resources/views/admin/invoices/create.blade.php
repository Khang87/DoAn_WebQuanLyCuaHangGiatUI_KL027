@extends('layouts.app')

@section('title', 'Tạo Hóa Đơn - Sky Laundry')
@section('page-title', 'Tạo hóa đơn')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Thông tin hóa đơn</h5>
            <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('invoices.store') }}" method="POST">
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Đơn hàng</label>
                    <select class="form-select" name="order_id">
                        <option value="">Chọn đơn hàng</option>
                        @foreach($orders as $order)
                        <option value="{{ $order->id }}">{{ $order->code }} - {{ $order->customer?->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tổng tiền</label>
                    <input type="number" class="form-control" name="total" placeholder="250000" min="0" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Ghi chú</label>
                    <textarea class="form-control" name="notes" rows="3"></textarea>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary">Hủy</a>
                <button type="submit" class="btn btn-primary">Lưu hóa đơn</button>
            </div>
        </form>
    </div>
</div>
@endsection
