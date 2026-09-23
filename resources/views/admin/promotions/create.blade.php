@extends('layouts.app')
@section('title', 'Thêm Khuyến Mãi - Giặt Ủi Pro')
@section('page-title', 'Thêm khuyến mãi')
@section('content')
<div class="card"><div class="card-body"><form action="{{ route('promotions.store') }}" method="POST">@csrf<h5 class="mb-4">Thông tin chương trình</h5><div class="row g-3"><div class="col-md-6"><label class="form-label">Tên chương trình</label><input class="form-control" name="name" required></div><div class="col-md-6"><label class="form-label">Mã khuyến mãi</label><input class="form-control" name="code" required></div><div class="col-md-6"><label class="form-label">Mức giảm</label><input class="form-control" name="discount" placeholder="10% hoặc 50,000 VNĐ" required></div><div class="col-md-6"><label class="form-label">Ngày hết hạn</label><input type="date" class="form-control" name="expires_at" required></div></div><div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('promotions.index') }}" class="btn btn-outline-secondary">Hủy</a><button class="btn btn-primary">Lưu khuyến mãi</button></div></form></div></div>
@endsection
