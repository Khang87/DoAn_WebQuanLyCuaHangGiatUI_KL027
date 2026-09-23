@extends('layouts.app')
@section('title', 'Sửa Khuyến Mãi - Giặt Ủi Pro')
@section('page-title', 'Sửa khuyến mãi')
@section('content')
<div class="card"><div class="card-body"><form action="{{ route('promotions.update', $id) }}" method="POST">@csrf @method('PUT')<h5 class="mb-4">Cập nhật chương trình</h5><div class="row g-3"><div class="col-md-6"><label class="form-label">Tên chương trình</label><input class="form-control" name="name" value="Giảm 10% đơn từ 200,000 VNĐ" required></div><div class="col-md-6"><label class="form-label">Mã khuyến mãi</label><input class="form-control" name="code" value="GIAT10" required></div><div class="col-md-6"><label class="form-label">Mức giảm</label><input class="form-control" name="discount" value="10%" required></div><div class="col-md-6"><label class="form-label">Ngày hết hạn</label><input type="date" class="form-control" name="expires_at" value="2026-09-30" required></div></div><div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('promotions.index') }}" class="btn btn-outline-secondary">Hủy</a><button class="btn btn-primary">Lưu thay đổi</button></div></form></div></div>
@endsection
