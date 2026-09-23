@extends('layouts.app')
@section('title', 'Sửa Bảng Giá - Giặt Ủi Pro')
@section('page-title', 'Sửa bảng giá')
@section('content')
<div class="card"><div class="card-body"><form action="{{ route('pricings.update', $id) }}" method="POST">@csrf @method('PUT') @include('admin.partials.simple-form', ['title' => 'Cập nhật bảng giá', 'nameLabel' => 'Tên dịch vụ', 'unitLabel' => 'Đơn vị tính', 'nameValue' => 'Giặt thường', 'unitValue' => 'kg'])</form></div></div>
@endsection
