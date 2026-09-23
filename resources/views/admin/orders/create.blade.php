@extends('layouts.app')

@section('title', 'Tạo Đơn Hàng Mới - Giặt Ủi Pro')
@section('page-title', 'Tạo Đơn Hàng Mới')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Thông tin đơn hàng</h5>
            <a href="{{ route('orders.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('orders.store') }}" method="POST">
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Khách hàng</label>
                    <select class="form-select" name="customer_id" required>
                        <option value="">-- Chọn khách hàng --</option>
                        <option value="1">Nguyễn Văn A</option>
                        <option value="2">Trần Thị B</option>
                        <option value="3">Phạm Thị C</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label">Chọn dịch vụ</label>
                    <p class="service-choice-help">Bạn có thể mô tả cụ thể về đồ cần giặt ở bước Ghi chú.</p>
                    <div class="service-choice-list">
                        <label class="service-choice">
                            <input type="radio" name="service_id" value="1" required>
                            <span>Giặt thường</span>
                        </label>
                        <label class="service-choice">
                            <input type="radio" name="service_id" value="2">
                            <span>Giặt khô</span>
                        </label>
                        <label class="service-choice">
                            <input type="radio" name="service_id" value="3">
                            <span>Ủi đồ</span>
                        </label>
                        <label class="service-choice">
                            <input type="radio" name="service_id" value="4">
                            <span>Giặt chăn mền</span>
                        </label>
                        <label class="service-choice">
                            <input type="radio" name="service_id" value="5">
                            <span>Giặt giày</span>
                        </label>
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Khối lượng</label>
                    <input type="text" class="form-control" name="weight_kg" value="5kg" placeholder="Ví dụ: 5kg" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Mô tả số lượng</label>
                    <input type="text" class="form-control" name="quantity_items" value="đồ thường + 3 áo trắng" placeholder="Ví dụ: đồ thường + áo sơ mi, 2 đôi tất" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Tổng tiền</label>
                    <input type="text" class="form-control" name="total_amount" value="250,000" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Trạng thái</label>
                    <select class="form-select" name="status">
                        <option value="pending">Chờ xử lý</option>
                        <option value="processing" selected>Đang xử lý</option>
                        <option value="completed">Hoàn thành</option>
                        <option value="cancelled">Đã hủy</option>
                    </select>
                </div>

            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="reset" class="btn btn-order-secondary order-form-btn">Làm mới</button>
                <button type="submit" class="btn btn-order-primary order-form-btn">
                    <i class="bi bi-check-lg me-1"></i>Lưu đơn hàng
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
