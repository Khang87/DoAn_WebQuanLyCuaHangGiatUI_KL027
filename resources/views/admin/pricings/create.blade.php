@extends('layouts.app')
@section('title', 'Thêm Bảng Giá - Sky Laundry')
@section('page-title', 'Thêm bảng giá')
@section('content')
<div class="card"><div class="card-body"><form action="{{ route('pricings.store') }}" method="POST">@csrf @include('admin.partials.simple-form', ['title' => 'Thông tin bảng giá', 'nameLabel' => 'Tên dịch vụ', 'unitLabel' => 'Đơn vị tính'])</form></div></div>
@endsection
