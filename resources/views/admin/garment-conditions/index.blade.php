@extends('layouts.app')

@section('title', 'Điều kiện đồ giặt - Sky Laundry')
@section('page-title', 'Điều kiện đồ giặt')

@section('content')
<div class="alert alert-warning" role="alert">
    <h5 class="alert-heading">Chức năng chưa được hỗ trợ bởi schema hiện tại</h5>
    <p class="mb-3">
        Supabase chưa có bảng lưu điều kiện đồ giặt. Để tránh ghi dữ liệu vào một bảng khác
        hoặc làm thay đổi cấu trúc cơ sở dữ liệu, chức năng thêm, sửa và xóa hiện không khả dụng.
    </p>
    <a href="{{ route('garment-categories.index') }}" class="btn btn-outline-primary">
        <i class="bi bi-arrow-left me-1"></i>Quản lý loại đồ giặt
    </a>
</div>
@endsection
