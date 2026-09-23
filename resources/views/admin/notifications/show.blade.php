@extends('layouts.app')
@section('title', 'Chi Tiết Thông Báo - Giặt Ủi Pro')
@section('page-title', 'Chi tiết thông báo')
@section('content')
<div class="card"><div class="card-body"><h4>Đơn hàng #DH001 đã hoàn thành</h4><p class="text-muted">Đơn của Nguyễn Văn A đã sẵn sàng giao khách.</p><a href="{{ route('notifications.index') }}" class="btn btn-outline-secondary">Quay lại</a></div></div>
@endsection
