@extends('layouts.app')
@section('title', 'Bảng Giá - Giặt Ủi Pro')
@section('page-title', 'Bảng Giá')
@section('content')
<div class="order-toolbar"><p class="text-muted mb-0">Giá dịch vụ theo kg, món, cái hoặc đôi.</p><a href="{{ route('pricings.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Thêm bảng giá</a></div>
<div class="card"><div class="card-body p-0"><div class="table-responsive"><table class="table-custom mb-0"><thead><tr><th>Dịch vụ</th><th>Loại đồ</th><th>Đơn vị</th><th>Đơn giá</th><th>Hiệu lực</th><th>Thao tác</th></tr></thead><tbody>
@foreach ([['Giặt thường', 'Đồ thường', 'kg', '25,000 VNĐ'], ['Giặt khô', 'Áo sơ mi', 'cái', '45,000 VNĐ'], ['Ủi đồ', 'Quần áo', 'món', '15,000 VNĐ'], ['Giặt chăn mền', 'Chăn mền', 'món', '80,000 VNĐ'], ['Giặt giày', 'Giày thể thao', 'đôi', '60,000 VNĐ']] as $item)
<tr><td><strong>{{ $item[0] }}</strong></td><td>{{ $item[1] }}</td><td>{{ $item[2] }}</td><td class="fw-semibold">{{ $item[3] }}</td><td><span class="badge-status badge-completed">Đang áp dụng</span></td><td><a href="{{ route('pricings.edit', $loop->iteration) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a></td></tr>
@endforeach
</tbody></table></div></div></div>
@endsection
