@extends('layouts.app')

@section('title', 'Thêm Khách Hàng - Giặt Ủi Pro')
@section('page-title', 'Thêm Khách Hàng')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0">Thông tin khách hàng</h5>
            <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Quay lại
            </a>
        </div>

        <form action="{{ route('customers.store') }}" method="POST">
            @csrf
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Họ tên</label>
                    <input type="text" class="form-control" name="name" placeholder="Nhập họ tên" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" placeholder="Nhập email" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Số điện thoại</label>
                    <input type="text" class="form-control" name="phone" placeholder="Nhập số điện thoại" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Loại khách hàng</label>
                    <select class="form-select" name="type">
                        <option value="new">Mới</option>
                        <option value="regular">Thường</option>
                        <option value="vip">VIP</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Địa chỉ</label>
                    <textarea class="form-control" name="address" rows="3" placeholder="Nhập địa chỉ"></textarea>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="reset" class="btn btn-outline-secondary">Làm mới</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Lưu khách hàng
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
