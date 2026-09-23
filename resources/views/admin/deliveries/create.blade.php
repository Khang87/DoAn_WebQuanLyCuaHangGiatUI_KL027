@extends('layouts.app')

@section('title', 'Tạo Giao Nhận - Giặt Ủi Pro')
@section('page-title', 'Tạo giao nhận')

@section('content')
<div class="delivery-form-shell">
    <div class="delivery-stepper" aria-label="Tiến trình đặt đơn">
        <div class="delivery-step active">
            <span>1</span>
            <small>Dịch vụ</small>
        </div>
        <div class="delivery-step-line active"></div>
        <div class="delivery-step active">
            <span>2</span>
            <small>Giao nhận</small>
        </div>
        <div class="delivery-step-line"></div>
        <div class="delivery-step">
            <span>3</span>
            <small>Ghi chú</small>
        </div>
    </div>

    <form action="{{ route('deliveries.store') }}" method="POST">
        @csrf
        <section class="delivery-section">
            <h2>Hình thức giao nhận</h2>
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
        </section>

        <section class="delivery-section">
            <h2>Địa chỉ lấy &amp; giao đồ</h2>
            <label class="delivery-address-card">
                <i class="bi bi-geo-alt-fill"></i>
                <span>
                    <strong>Nhà riêng</strong>
                    <small>123 Đường Điện Biên Phủ, Phường 15, Bình Thạnh, TP.HCM</small>
                </span>
                <button type="button" class="delivery-change-btn">Thay đổi</button>
            </label>
            <input type="hidden" name="address" value="123 Đường Điện Biên Phủ, Phường 15, Bình Thạnh, TP.HCM">
        </section>

        <section class="delivery-section">
            <h2>Thời gian lấy đồ</h2>
            <div class="delivery-time-grid">
                <label class="delivery-input-wrap">
                    <i class="bi bi-calendar4"></i>
                    <input type="date" name="pickup_date" value="2026-09-25" required>
                </label>
                <label class="delivery-input-wrap">
                    <i class="bi bi-clock"></i>
                    <input type="time" name="pickup_time" value="09:00" required>
                </label>
            </div>
        </section>

        <div class="delivery-form-actions">
            <a href="{{ route('deliveries.index') }}" class="btn btn-outline-primary">Quay lại</a>
            <button type="submit" class="btn btn-primary">Tiếp theo <i class="bi bi-arrow-right ms-1"></i></button>
        </div>
    </form>
</div>
@endsection
