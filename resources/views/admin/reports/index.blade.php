@extends('layouts.app')
@section('title', 'Báo Cáo - Giặt Ủi Pro')
@section('page-title', 'Báo Cáo')
@section('content')
<div class="row g-4 mb-4"><div class="col-md-4"><div class="card"><div class="card-body"><span class="text-muted">Doanh thu tháng này</span><h3 class="mt-2 mb-0">18,450,000 VNĐ</h3><small class="text-success">+12.5% so với tháng trước</small></div></div></div><div class="col-md-4"><div class="card"><div class="card-body"><span class="text-muted">Đơn hàng hoàn thành</span><h3 class="mt-2 mb-0">128</h3><small class="text-success">+8 đơn trong tuần này</small></div></div></div><div class="col-md-4"><div class="card"><div class="card-body"><span class="text-muted">Khách hàng mới</span><h3 class="mt-2 mb-0">36</h3><small class="text-success">+15% so với tháng trước</small></div></div></div></div>
<div class="card"><div class="card-body"><h5 class="mb-3">Báo cáo chi tiết</h5><div class="d-flex flex-wrap gap-2"><a href="{{ route('reports.revenue') }}" class="btn btn-outline-primary"><i class="bi bi-graph-up me-1"></i>Doanh thu</a><a href="{{ route('reports.orders') }}" class="btn btn-outline-primary"><i class="bi bi-bag me-1"></i>Đơn hàng</a><a href="{{ route('reports.customers') }}" class="btn btn-outline-primary"><i class="bi bi-people me-1"></i>Khách hàng</a></div></div></div>
@endsection
