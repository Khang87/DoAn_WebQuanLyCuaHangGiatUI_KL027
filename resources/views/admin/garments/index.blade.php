@extends('layouts.app')

@section('title', 'Quản Lý Loại Đồ Giặt - Giặt Ủi Pro')
@section('page-title', 'Quản Lý Loại Đồ Giặt & Tình Trạng Sản Phẩm')

@section('content')
<!-- Page Actions -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="{{ route('garments.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus me-2"></i>Thêm Loại Đồ Giặt
        </a>
    </div>
    <form action="{{ route('garments.index') }}" method="GET" class="d-flex gap-2">
        <div class="input-group" style="width: 260px;">
            <input type="text" name="search" class="form-control" placeholder="Tìm kiếm loại đồ, hiện trạng..." value="{{ request('search') }}">
            <button class="btn btn-outline-secondary" type="submit">
                <i class="bi bi-search"></i>
            </button>
        </div>
        <select name="category" class="form-select" style="width: auto;" onchange="this.form.submit()">
            <option value="">Tất cả danh mục</option>
            <option value="Trang phục truyền thống" @selected(request('category') === 'Trang phục truyền thống')>Trang phục truyền thống</option>
            <option value="Đồ cao cấp" @selected(request('category') === 'Đồ cao cấp')>Đồ cao cấp</option>
            <option value="Áo khoác" @selected(request('category') === 'Áo khoác')>Áo khoác</option>
            <option value="Đồ công sở" @selected(request('category') === 'Đồ công sở')>Đồ công sở</option>
            <option value="Hàng ngày" @selected(request('category') === 'Hàng ngày')>Hàng ngày</option>
        </select>
    </form>
</div>

@php
    function getGarmentIcon($garment) {
        $name = mb_strtolower($garment->name ?? '');
        $cat = mb_strtolower($garment->category ?? '');

        if (str_contains($name, 'dài') || str_contains($name, 'váy') || str_contains($name, 'đầm') || str_contains($cat, 'truyền thống')) {
            return 'fa-solid fa-person-dress';
        }
        if (str_contains($name, 'khoác') || str_contains($name, 'blazer') || str_contains($name, 'suit') || str_contains($name, 'vest')) {
            return 'fa-solid fa-user-tie';
        }
        if (str_contains($name, 'quần') || str_contains($cat, 'công sở')) {
            return 'fa-solid fa-scissors';
        }
        if (str_contains($name, 'chăn') || str_contains($name, 'mền') || str_contains($name, 'ga') || str_contains($name, 'gối')) {
            return 'fa-solid fa-bed';
        }
        if (str_contains($name, 'giày') || str_contains($name, 'dép') || str_contains($name, 'vớ') || str_contains($name, 'tất')) {
            return 'fa-solid fa-shoe-prints';
        }

        return 'fa-solid fa-shirt';
    }

    $sampleGarments = [
        (object)['id' => 1, 'name' => 'Áo Dài Truyền Thống', 'category' => 'Trang phục truyền thống', 'price' => 70000, 'condition_note' => 'Cần kiểm tra kỹ đường chỉ, rách nhẹ ở tà hoặc ố vàng', 'status' => 'active'],
        (object)['id' => 2, 'name' => 'Váy Cưới / Đầm Dạ Hội', 'category' => 'Đồ cao cấp', 'price' => 150000, 'condition_note' => 'Kiểm tra đính hạt, vết ố rượu bia/đồ ăn trước khi giặt khô', 'status' => 'active'],
        (object)['id' => 3, 'name' => 'Áo Khoác Dạ / Blazer', 'category' => 'Áo khoác', 'price' => 60000, 'condition_note' => 'Xù lông nhẹ, đứt cúc áo, sờn cổ tay', 'status' => 'active'],
        (object)['id' => 4, 'name' => 'Quần Tây / Quần Ân', 'category' => 'Đồ công sở', 'price' => 30000, 'condition_note' => 'Mất nếp gấp, sờn gấu quần', 'status' => 'active'],
        (object)['id' => 5, 'name' => 'Đồ Thường / Áo Thun', 'category' => 'Hàng ngày', 'price' => 20000, 'condition_note' => 'Tình trạng bình thường', 'status' => 'active'],
    ];
    $displayGarments = $garments->count() > 0 ? $garments : collect($sampleGarments);
@endphp

<!-- Garments Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr>
                        <th>Tên Loại Đồ</th>
                        <th>Danh Mục</th>
                        <th>Giá Dịch Vụ</th>
                        <th>Mô Tả Hiện Trạng Trước Khi Giặt</th>
                        <th>Trạng Thái</th>
                        <th>Thao Tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($displayGarments as $garment)
                    @php $garmentIcon = getGarmentIcon($garment); @endphp
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="bg-primary-subtle text-primary rounded-circle p-2 me-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                    <i class="{{ $garmentIcon }}"></i>
                                </div>
                                <strong class="fw-semibold">{{ $garment->name }}</strong>
                            </div>
                        </td>
                        <td><span class="badge bg-light text-dark border">{{ $garment->category ?: 'Đồ giặt' }}</span></td>
                        <td><strong class="text-primary">{{ number_format($garment->price) }} VNĐ</strong></td>
                        <td><small class="text-muted">{{ $garment->condition_note ?: 'Chưa ghi nhận hiện trạng' }}</small></td>
                        <td><span class="badge {{ $garment->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ $garment->status === 'active' ? 'Đang hoạt động' : 'Tạm ngưng' }}</span></td>
                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('garments.show', $garment->id) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('garments.edit', $garment->id) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('garments.destroy', $garment->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa loại đồ giặt này?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-order-action delete" title="Xóa"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Chưa có thông tin loại đồ giặt nào.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
