@extends('layouts.app')
@section('title', 'Sửa Loại Đồ - Giặt Ủi Pro')
@section('page-title', 'Sửa loại đồ giặt')
@section('content')
<div class="card"><div class="card-body"><form action="{{ route('garments.update', $id) }}" method="POST">@csrf @method('PUT') @include('admin.partials.simple-form', ['title' => 'Cập nhật loại đồ', 'nameLabel' => 'Tên loại đồ', 'unitLabel' => 'Đơn vị tính', 'nameValue' => 'Áo sơ mi', 'unitValue' => 'cái'])</form></div></div>
@endsection
