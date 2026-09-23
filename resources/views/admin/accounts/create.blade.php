@extends('layouts.app')
@section('title', 'Thêm Tài Khoản - Giặt Ủi Pro')
@section('page-title', 'Thêm tài khoản')
@section('content')
<div class="card"><div class="card-body"><form action="{{ route('accounts.store') }}" method="POST">@csrf<h5 class="mb-4">Thông tin tài khoản</h5><div class="row g-3"><div class="col-md-6"><label class="form-label">Họ tên</label><input class="form-control" name="name" required></div><div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required></div><div class="col-md-6"><label class="form-label">Vai trò</label><select class="form-select" name="role"><option>Nhân viên</option><option>Quản trị viên</option></select></div><div class="col-md-6"><label class="form-label">Mật khẩu</label><input type="password" class="form-control" name="password" required></div></div><div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('accounts.index') }}" class="btn btn-outline-secondary">Hủy</a><button class="btn btn-primary">Tạo tài khoản</button></div></form></div></div>
@endsection
