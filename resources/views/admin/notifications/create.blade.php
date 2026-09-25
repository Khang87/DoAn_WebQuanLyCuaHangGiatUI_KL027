@extends('layouts.app')
@section('title', 'Tạo Thông Báo - Sky Laundry')
@section('page-title', 'Tạo thông báo')
@section('content')
<div class="card"><div class="card-body"><form action="{{ route('notifications.store') }}" method="POST">@csrf<h5 class="mb-4">Nội dung thông báo</h5><div class="mb-3"><label class="form-label">Tiêu đề</label><input class="form-control" name="title" required></div><div class="mb-3"><label class="form-label">Nội dung</label><textarea class="form-control" name="message" rows="5" required></textarea></div><div class="d-flex justify-content-end gap-2"><a href="{{ route('notifications.index') }}" class="btn btn-outline-secondary">Hủy</a><button class="btn btn-primary">Đăng thông báo</button></div></form></div></div>
@endsection
