@extends('layouts.app')
@section('title', 'Sửa Tài Khoản - Giặt Ủi Pro')
@section('page-title', 'Sửa tài khoản')
@section('content')
<div class="card"><div class="card-body"><form action="{{ route('accounts.update', $id) }}" method="POST">@csrf @method('PUT')<h5 class="mb-4">Cập nhật tài khoản</h5><div class="row g-3"><div class="col-md-6"><label class="form-label">Họ tên</label><input class="form-control" name="name" value="Nhân viên cửa hàng" required></div><div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="staff@giatui.com" required></div><div class="col-md-6"><label class="form-label">Vai trò</label><select class="form-select" name="role"><option selected>Nhân viên</option><option>Quản trị viên</option></select></div></div><div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('accounts.index') }}" class="btn btn-outline-secondary">Hủy</a><button class="btn btn-primary">Lưu thay đổi</button></div></form></div></div>
@endsection
