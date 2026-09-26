@extends('layouts.app')
@section('title', 'Sửa thông báo - Sky Laundry')
@section('page-title', 'Sửa thông báo')
@section('content')
<div class="card"><div class="card-body"><form action="{{ route('notifications.update', $id) }}" method="POST">@csrf @method('PUT')<label class="form-label">Tiêu đề</label><input class="form-control mb-3" name="title" value="Đơn hàng #DH001 đã hoàn thành"><label class="form-label">Nội dung</label><textarea class="form-control" name="message" rows="4">Đơn của Nguyễn Văn A đã sẵn sàng giao khách.</textarea><button class="btn btn-primary mt-4">Lưu thay đổi</button></form></div></div>
@endsection
