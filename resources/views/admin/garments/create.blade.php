@extends('layouts.app')
@section('title', 'Thêm Loại Đồ - Giặt Ủi Pro')
@section('page-title', 'Thêm loại đồ giặt')
@section('content')
<div class="card"><div class="card-body"><form action="{{ route('garments.store') }}" method="POST">@csrf @include('admin.partials.simple-form', ['title' => 'Thông tin loại đồ', 'nameLabel' => 'Tên loại đồ', 'unitLabel' => 'Đơn vị tính'])</form></div></div>
@endsection
