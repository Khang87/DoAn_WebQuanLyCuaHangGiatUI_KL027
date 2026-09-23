@extends('layouts.app')
@section('title', 'Thông Báo - Giặt Ủi Pro')
@section('page-title', 'Thông Báo')
@section('content')
<div class="order-toolbar"><p class="text-muted mb-0">Các cập nhật mới từ hoạt động cửa hàng.</p><a href="{{ route('notifications.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Tạo thông báo</a></div>
<div class="card"><div class="card-body"><div class="list-group list-group-flush"><div class="list-group-item px-0 d-flex gap-3"><i class="bi bi-bag-check text-success fs-4"></i><div><strong>Đơn hàng #DH001 đã hoàn thành</strong><p class="text-muted mb-0">Đơn của Nguyễn Văn A đã sẵn sàng giao khách. 10 phút trước.</p></div></div><div class="list-group-item px-0 d-flex gap-3"><i class="bi bi-truck text-primary fs-4"></i><div><strong>Lịch lấy đồ mới</strong><p class="text-muted mb-0">Trần Thị B có lịch mang đồ đến cửa hàng lúc 14:30 hôm nay.</p></div></div><div class="list-group-item px-0 d-flex gap-3"><i class="bi bi-exclamation-circle text-warning fs-4"></i><div><strong>Sắp hết vật tư</strong><p class="text-muted mb-0">Nước giặt chuyên dụng còn dưới mức tồn kho tối thiểu.</p></div></div></div></div></div>
@endsection
