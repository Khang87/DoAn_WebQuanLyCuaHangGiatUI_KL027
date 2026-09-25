@extends('layouts.app')
@section('title', 'Sửa Bảng Giá - Sky Laundry')
@section('page-title', 'Sửa bảng giá')
@section('content')
<div class="card"><div class="card-body"><form action="{{ route('pricings.update', $pricing) }}" method="POST">@csrf @method('PUT')
    <div class="row g-3">
        <div class="col-12"><label class="form-label">Tên bảng giá</label><input class="form-control" name="name" value="{{ $pricing->name }}" required></div>
        <div class="col-md-6"><label class="form-label">Đơn vị tính</label><input class="form-control" name="unit" value="{{ $pricing->unit ?? 'kg' }}" required></div>
        <div class="col-md-6"><label class="form-label">Giá</label><input type="number" class="form-control" name="price" value="{{ $pricing->price }}" min="0" required></div>
        <div class="col-md-6"><label class="form-label">Trạng thái</label><select class="form-select" name="status"><option value="active" @selected($pricing->status === 'active')>Đang hoạt động</option><option value="inactive" @selected($pricing->status === 'inactive')>Tạm ngừng</option></select></div>
        <div class="col-12"><label class="form-label">Ghi chú</label><textarea class="form-control" name="description" rows="3">{{ $pricing->description ?? '' }}</textarea></div>
    </div>
    <button class="btn btn-primary mt-4">Lưu thay đổi</button>
</form></div></div>
@endsection
