@extends('layouts.app')
@section('title', 'Loại Đồ Giặt - Giặt Ủi Pro')
@section('page-title', 'Loại Đồ Giặt')
@section('content')
<div class="order-toolbar"><p class="text-muted mb-0">Danh mục các loại đồ và cách tính số lượng.</p><a href="{{ route('garments.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Thêm loại đồ</a></div>
<div class="card"><div class="card-body p-0"><div class="table-responsive"><table class="table-custom mb-0"><thead><tr><th>Mã</th><th>Loại đồ</th><th>Cách tính</th><th>Đơn giá tham khảo</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
@foreach ([['AO', 'Áo sơ mi', 'Theo cái', '15,000 VNĐ/cái'], ['QUAN', 'Quần dài', 'Theo cái', '20,000 VNĐ/cái'], ['CHAN', 'Chăn mền', 'Theo món', '80,000 VNĐ/món'], ['GIAY', 'Giày thể thao', 'Theo đôi', '60,000 VNĐ/đôi']] as $item)
<tr><td><strong>{{ $item[0] }}</strong></td><td>{{ $item[1] }}</td><td>{{ $item[2] }}</td><td>{{ $item[3] }}</td><td><span class="badge-status badge-completed">Đang dùng</span></td><td><a href="{{ route('garments.edit', $loop->iteration) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a></td></tr>
@endforeach
</tbody></table></div></div></div>
@endsection
