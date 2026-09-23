@extends('layouts.app')

@section('title', 'Sửa Giao Nhận - Giặt Ủi Pro')
@section('page-title', 'Sửa giao nhận')

@section('content')
<div class="delivery-form-shell">
    <div class="card">
        <div class="card-body">
            <h2 class="h5 mb-4">Cập nhật lịch giao nhận</h2>
            <form action="{{ route('deliveries.update', $id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-4">
                    <label class="form-label">Hình thức giao nhận</label>
                    <div class="delivery-methods">
                        <label class="delivery-method selected">
                            <input type="radio" name="method" value="pickup" checked>
                            <i class="bi bi-truck"></i>
                            <span>Lấy tận nơi</span>
                        </label>
                        <label class="delivery-method">
                            <input type="radio" name="method" value="dropoff">
                            <i class="bi bi-shop"></i>
                            <span>Mang đến cửa hàng</span>
                        </label>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Địa chỉ lấy &amp; giao đồ</label>
                        <input type="text" class="form-control" name="address" value="123 Đường Điện Biên Phủ, Phường 15, Bình Thạnh, TP.HCM" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Ngày lấy đồ</label>
                        <input type="date" class="form-control" name="pickup_date" value="2026-09-25" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Giờ lấy đồ</label>
                        <input type="time" class="form-control" name="pickup_time" value="09:00" required>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('deliveries.show', $id) }}" class="btn btn-outline-secondary">Hủy</a>
                    <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
