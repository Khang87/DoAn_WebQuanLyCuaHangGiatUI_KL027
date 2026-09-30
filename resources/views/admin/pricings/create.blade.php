@extends('layouts.app')
@section('title', 'Thêm bảng giá - Sky Laundry')
@section('page-title', 'Thêm bảng giá')
@section('content')
@php
    $serviceOptions = $services ?? \App\Models\DichVu::where('TrangThai', 'Hoạt động')->orderBy('TenDichVu')->get();
    $garmentOptions = $garments ?? \App\Models\LoaiDoGiat::where('TrangThai', 'Hoạt động')->orderBy('TenLoaiDoGiat')->get();
    $unitOptions = \App\Models\DonViTinh::where('TrangThai', 'Hoạt động')->orderBy('TenDonViTinh')->get();
@endphp
<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-4"><h5 class="mb-0">Thông tin bảng giá</h5><a href="{{ route('pricings.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Quay lại</a></div>
    <form action="{{ route('pricings.store') }}" method="POST">@csrf
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="service_id">Dịch vụ <span class="text-danger ms-1">*</span></label><select class="form-select" id="service_id" name="service_id" required><option value="">-- Chọn dịch vụ --</option>@foreach($serviceOptions as $service)<option value="{{ $service->DichVuID }}" @selected(old('service_id') == $service->DichVuID)>{{ $service->TenDichVu }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label" for="garment_id">Loại đồ giặt <span class="text-danger ms-1">*</span></label><select class="form-select" id="garment_id" name="garment_id" required><option value="">-- Chọn loại đồ --</option>@foreach($garmentOptions as $garment)<option value="{{ $garment->LoaiDoGiatID }}" @selected(old('garment_id') == $garment->LoaiDoGiatID)>{{ $garment->TenLoaiDoGiat }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label" for="unit">Đơn vị tính <span class="text-danger ms-1">*</span></label><select class="form-select" id="unit" name="unit" required><option value="">-- Chọn đơn vị --</option>@foreach($unitOptions as $unit)<option value="{{ $unit->DonViTinhID }}" @selected(old('unit') == $unit->DonViTinhID)>{{ $unit->TenDonViTinh }} ({{ $unit->KyHieu }})</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label" for="price">Đơn giá (VNĐ) <span class="text-danger ms-1">*</span></label><input type="number" class="form-control" id="price" name="price" value="{{ old('price') }}" min="0" step="100" required></div>
            <div class="col-md-4"><label class="form-label" for="effective_date">Ngày áp dụng <span class="text-danger ms-1">*</span></label><input type="date" class="form-control" id="effective_date" name="effective_date" value="{{ old('effective_date', now()->format('Y-m-d')) }}" required></div>
            <div class="col-md-6"><label class="form-label" for="status">Trạng thái <span class="text-danger ms-1">*</span></label><x-admin.status-select name="status" id="status" :options="\App\Enums\RecordStatus::options()" selected="active" class="form-select" required /></div>
            <div class="col-12"><label class="form-label" for="description">Mô tả</label><textarea class="form-control" id="description" name="description" rows="3">{{ old('description') }}</textarea></div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('pricings.index') }}" class="btn btn-outline-secondary">Hủy</a><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Lưu bảng giá</button></div>
    </form>
</div></div>
@endsection