@extends('layouts.app')
@section('title', 'Chi Tiết Tài Khoản - Giặt Ủi Pro')
@section('page-title', 'Chi tiết tài khoản')
@section('content')
<div class="card"><div class="card-body"><h4>Nhân viên cửa hàng</h4><p class="mb-1">staff@giatui.com</p><p class="text-muted">Vai trò: Nhân viên</p><a href="{{ route('accounts.index') }}" class="btn btn-outline-secondary">Quay lại</a><a href="{{ route('accounts.edit', $id) }}" class="btn btn-primary">Chỉnh sửa</a></div></div>
@endsection
