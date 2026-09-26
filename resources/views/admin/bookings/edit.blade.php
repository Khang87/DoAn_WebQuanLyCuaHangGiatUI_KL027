@extends('layouts.app')

@section('title', 'Chỉnh sửa đặt lịch - Sky Laundry')
@section('page-title', 'Chỉnh sửa đặt lịch')

@section('content')
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4"><h5 class="mb-0">Chỉnh sửa đặt lịch</h5><a href="{{ route('bookings.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Quay lại</a></div>
        <form action="{{ route('bookings.update', $booking) }}" method="POST">
            @csrf @method('PUT')
            <div class="row g-4">
                <div class="col-md-6"><label class="form-label">Khách hàng <span class="text-danger ms-1">*</span></label><select class="form-select @error('customer_id') is-invalid @enderror" name="customer_id" required><option value="">Chọn khách hàng</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id', $booking->customer_id) == $customer->id)>{{ $customer->name }}</option>@endforeach</select>@error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label">Nhân viên phụ trách</label><select class="form-select @error('staff_id') is-invalid @enderror" name="staff_id"><option value="">Chưa phân công</option>@foreach($staff as $employee)<option value="{{ $employee->id }}" @selected(old('staff_id', $booking->staff_id) == $employee->id)>{{ $employee->name }}</option>@endforeach</select>@error('staff_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label">Hình thức <span class="text-danger ms-1">*</span></label><select class="form-select @error('method') is-invalid @enderror" name="method" required><option value="nhan_do" @selected(old('method', $booking->method) === 'nhan_do')>Nhận đồ</option><option value="giao_do" @selected(old('method', $booking->method) === 'giao_do')>Giao đồ</option></select>@error('method')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label">Ngày hẹn <span class="text-danger ms-1">*</span></label><input type="date" class="form-control @error('scheduled_date') is-invalid @enderror" name="scheduled_date" value="{{ old('scheduled_date', $booking->scheduled_date?->format('Y-m-d')) }}" required>@error('scheduled_date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label">Giờ hẹn <span class="text-danger ms-1">*</span></label><input type="time" class="form-control @error('scheduled_time') is-invalid @enderror" name="scheduled_time" value="{{ old('scheduled_time', $booking->scheduled_time?->format('H:i')) }}" required>@error('scheduled_time')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label">Trạng thái</label><select class="form-select @error('status') is-invalid @enderror" name="status"><option value="pending" @selected(old('status', $booking->status) === 'pending')>Chờ xác nhận</option><option value="confirmed" @selected(old('status', $booking->status) === 'confirmed')>Đã xác nhận</option><option value="arrived" @selected(old('status', $booking->status) === 'arrived')>Đã đến nơi</option><option value="completed" @selected(old('status', $booking->status) === 'completed')>Hoàn thành</option><option value="cancelled" @selected(old('status', $booking->status) === 'cancelled')>Đã hủy</option></select>@error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><label class="form-label">Ghi chú</label><textarea class="form-control" name="notes" rows="3">{{ old('notes', $booking->notes) }}</textarea></div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4"><button type="reset" class="btn btn-outline-secondary">Làm mới</button><button type="submit" class="btn btn-primary">Cập nhật</button></div>
        </form>
    </div>
</div>
@endsection
