@extends('layouts.app')
@section('title', 'Thêm bảng giá - Sky Laundry')
@section('page-title', 'Thêm bảng giá')
@section('content')
@php
    $serviceOptions = $services ?? \App\Models\DichVu::where('TrangThai', 'Hoạt động')->orderBy('TenDichVu')->get();
    $garmentOptions = $garments ?? \App\Models\LoaiDoGiat::where('TrangThai', 'Hoạt động')->orderBy('TenLoaiDoGiat')->get();
    $unitOptions = $units ?? \App\Models\DonViTinh::where('TrangThai', 'Hoạt động')->orderBy('TenDonViTinh')->get();
@endphp
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/libs/flatpickr/flatpickr.min.css') }}">
@endpush
<div class="card"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-4"><h5 class="mb-0">Thông tin bảng giá</h5><a href="{{ route('pricings.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Quay lại</a></div>
    <form action="{{ route('pricings.store') }}" method="POST">@csrf
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="DichVuID">Dịch vụ <span class="text-danger ms-1">*</span></label><select class="form-select @error('DichVuID') is-invalid @enderror" id="DichVuID" name="DichVuID" required><option value="">-- Chọn dịch vụ --</option>@foreach($serviceOptions as $service)<option value="{{ $service->DichVuID }}" @selected(old('DichVuID') == $service->DichVuID)>{{ $service->TenDichVu }}</option>@endforeach</select>@error('DichVuID')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label" for="LoaiDoGiatID">Loại đồ giặt <span class="text-danger ms-1">*</span></label><select class="form-select @error('LoaiDoGiatID') is-invalid @enderror" id="LoaiDoGiatID" name="LoaiDoGiatID" required><option value="">-- Chọn loại đồ --</option>@foreach($garmentOptions as $garment)<option value="{{ $garment->LoaiDoGiatID }}" @selected(old('LoaiDoGiatID') == $garment->LoaiDoGiatID)>{{ $garment->TenLoaiDoGiat }}</option>@endforeach</select>@error('LoaiDoGiatID')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-4"><label class="form-label" for="DonViTinhID">Đơn vị tính <span class="text-danger ms-1">*</span></label><select class="form-select @error('DonViTinhID') is-invalid @enderror" id="DonViTinhID" name="DonViTinhID" required><option value="">-- Chọn đơn vị --</option>@foreach($unitOptions as $unit)<option value="{{ $unit->DonViTinhID }}" @selected(old('DonViTinhID') == $unit->DonViTinhID)>{{ $unit->TenDonViTinh }} ({{ $unit->KyHieu }})</option>@endforeach</select>@error('DonViTinhID')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-4"><label class="form-label" for="DonGia">Đơn giá (VNĐ) <span class="text-danger ms-1">*</span></label><input type="number" class="form-control @error('DonGia') is-invalid @enderror" id="DonGia" name="DonGia" value="{{ old('DonGia') }}" min="0" step="0.01" required>@error('DonGia')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-4"><label class="form-label" for="NgayApDung_display">Ngày áp dụng <span class="text-danger ms-1">*</span></label><input type="text" inputmode="numeric" placeholder="dd-mm-yyyy" autocomplete="off" data-date-picker class="form-control @error('NgayApDung') is-invalid @enderror" id="NgayApDung_display" name="NgayApDung_display" value="{{ old('NgayApDung_display', now()->format('d-m-Y')) }}" required>@error('NgayApDung')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label" for="NgayKetThuc_display">Ngày kết thúc</label><input type="text" inputmode="numeric" placeholder="dd-mm-yyyy" autocomplete="off" data-date-picker class="form-control @error('NgayKetThuc') is-invalid @enderror" id="NgayKetThuc_display" name="NgayKetThuc_display" value="{{ old('NgayKetThuc_display') }}">@error('NgayKetThuc')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label" for="TrangThai">Trạng thái <span class="text-danger ms-1">*</span></label><x-admin.status-select name="TrangThai" id="TrangThai" :options="['Hoạt động' => 'Hoạt động', 'Hết hiệu lực' => 'Hết hiệu lực', 'Tạm ngưng' => 'Tạm ngưng']" selected="Hoạt động" class="form-select @error('TrangThai') is-invalid @enderror" required />@error('TrangThai')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('pricings.index') }}" class="btn btn-outline-secondary">Hủy</a><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Lưu bảng giá</button></div>
    </form>
</div></div>
@push('scripts')
<script src="{{ asset('assets/libs/flatpickr/flatpickr.min.js') }}"></script>
<script>
    document.querySelectorAll('[data-date-picker]').forEach((input) => {
        flatpickr(input, {
            allowInput: true,
            dateFormat: 'd-m-Y',
        });
    });
</script>
@endpush
@endsection