@extends('layouts.app')
@section('title', 'Sửa bảng giá - Sky Laundry')
@section('page-title', 'Sửa bảng giá')
@section('content')
@php
    $serviceOptions = $services ?? \App\Models\Service::where('status', 'active')->orderBy('name')->get();
    $garmentOptions = $garments ?? \App\Models\Garment::where('status', 'active')->orderBy('name')->get();
@endphp
<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-4"><h5 class="mb-0">Cập nhật bảng giá</h5><a href="{{ route('pricings.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Quay lại</a></div>
    <form action="{{ route('pricings.update', $pricing) }}" method="POST">@csrf @method('PUT')
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="service_id">Dịch vụ <span class="text-danger ms-1">*</span></label><select class="form-select" id="service_id" name="service_id" required><option value="">-- Chọn dịch vụ --</option>@foreach($serviceOptions as $service)<option value="{{ $service->id }}" @selected(old('service_id', $pricing->service_id) == $service->id)>{{ $service->name }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label" for="garment_id">Loại đồ giặt <span class="text-danger ms-1">*</span></label><select class="form-select" id="garment_id" name="garment_id" required><option value="">-- Chọn loại đồ --</option>@foreach($garmentOptions as $garment)<option value="{{ $garment->id }}" @selected(old('garment_id', $pricing->garment_id) == $garment->id)>{{ $garment->name }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label" for="unit">Đơn vị tính <span class="text-danger ms-1">*</span></label><select class="form-select" id="unit" name="unit" required><option value="kg" @selected(old('unit', $pricing->unit) === 'kg')>Kg</option><option value="item" @selected(old('unit', $pricing->unit) === 'item')>Món</option><option value="piece" @selected(old('unit', $pricing->unit) === 'piece')>Cái</option><option value="pair" @selected(old('unit', $pricing->unit) === 'pair')>Đôi</option></select></div>
            <div class="col-md-4"><label class="form-label" for="price">Đơn giá (VNĐ) <span class="text-danger ms-1">*</span></label><input type="number" class="form-control" id="price" name="price" value="{{ old('price', $pricing->price) }}" min="0" step="100" required></div>
            <div class="col-md-4"><label class="form-label" for="effective_date">Ngày áp dụng <span class="text-danger ms-1">*</span></label><input type="date" class="form-control" id="effective_date" name="effective_date" value="{{ old('effective_date', $pricing->effective_date?->format('Y-m-d') ?? $pricing->created_at?->format('Y-m-d')) }}" required></div>
            <div class="col-md-6"><label class="form-label" for="status">Trạng thái <span class="text-danger ms-1">*</span></label><select class="form-select" id="status" name="status" required><option value="active" @selected(old('status', $pricing->status) === 'active')>Đang áp dụng</option><option value="inactive" @selected(old('status', $pricing->status) === 'inactive')>Tạm ngừng</option></select></div>
            <div class="col-12"><label class="form-label" for="description">Mô tả</label><textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $pricing->description) }}</textarea></div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('pricings.index') }}" class="btn btn-outline-secondary">Hủy</a><button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Lưu thay đổi</button></div>
    </form>
</div></div>
@endsection