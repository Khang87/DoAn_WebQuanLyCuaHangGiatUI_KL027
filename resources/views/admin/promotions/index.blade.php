@extends('layouts.app')
@section('title', 'Khuyến Mãi - Giặt Ủi Pro')
@section('page-title', 'Khuyến Mãi')
@section('content')
<div class="order-toolbar"><p class="text-muted mb-0">Tạo và theo dõi các chương trình ưu đãi.</p><a href="{{ route('promotions.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Thêm khuyến mãi</a></div>
<div class="row g-4">
@foreach ([['GIAT10', 'Giảm 10% đơn từ 200,000 VNĐ', '10%', '30/09/2026', 'Đang chạy'], ['MEMBER50', 'Giảm 50,000 VNĐ cho khách VIP', '50,000 VNĐ', '15/10/2026', 'Sắp áp dụng'], ['GIAY20', 'Giảm 20% dịch vụ giặt giày', '20%', '31/10/2026', 'Đang chạy']] as $promo)
<div class="col-md-6 col-xl-4"><div class="card h-100"><div class="card-body"><div class="d-flex justify-content-between align-items-start mb-3"><span class="badge bg-primary">{{ $promo[0] }}</span><span class="badge-status badge-completed">{{ $promo[4] }}</span></div><h5>{{ $promo[1] }}</h5><p class="text-muted mb-3">Ưu đãi {{ $promo[2] }} · Hết hạn {{ $promo[3] }}</p><a href="{{ route('promotions.edit', $loop->iteration) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Chỉnh sửa</a></div></div></div>
@endforeach
</div>
@endsection
