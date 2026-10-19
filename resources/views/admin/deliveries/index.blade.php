@extends('layouts.app')
@section('title', 'Quản lý giao nhận - Sky Laundry')
@section('page-title', 'Quản lý giao nhận')
@section('content')
<!-- Page Actions: nút "Thêm" luôn nằm góc trên bên trái -->
<div class="page-toolbar">
    @can('deliveries.create')
        <a href="{{ route('deliveries.create') }}" class="btn btn-create">
            <i class="bi bi-plus-lg"></i>Thêm lịch giao nhận
        </a>
    @endcan
    <p class="text-muted page-toolbar__desc">Theo dõi lịch nhận và giao đồ cho khách hàng.</p>
</div>
<form action="{{ url()->current() }}" method="GET" class="row g-3 align-items-center mb-4">
    <div class="col-12 col-md-auto flex-grow-1">
        <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden filter-search">
            <span class="input-group-text bg-white border-end-0 ps-3"><i class="fas fa-search text-muted"></i></span>
            <input type="text" name="search" class="form-control form-control-sm border-start-0 ps-2" placeholder="Tìm mã đơn, mã Booking, khách hàng hoặc địa chỉ..." value="{{ request('search') }}">
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <select name="method" class="form-select form-select-sm filter-select shadow-sm rounded-3" onchange="this.form.submit()">
            <option value="">Tất cả hình thức</option>
            <option value="nhan_do" @selected(request('method') === 'nhan_do')>Nhận đồ</option>
            <option value="giao_do" @selected(request('method') === 'giao_do')>Giao đồ</option>
        </select>
    </div>
    <div class="col-12 col-sm-6 col-md-auto">
        <x-admin.status-select
            name="status"
            id="filter-status"
            :options="array_merge(\App\Enums\DeliveryStatus::options(), ['received' => 'Đã nhận đồ'])"
            placeholder="Tất cả trạng thái"
            class="form-select form-select-sm filter-select shadow-sm rounded-3"
            submit
        />
    </div>
</form>
<div class="card">
    <div class="card-header bg-white">
        <h2 class="h5 mb-1">Phiếu giao nhận vận hành</h2>
        <p class="small text-muted mb-0">Phiếu trong bảng GiaoNhan, gắn trực tiếp với đơn hàng.</p>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle table-custom">
                <thead class="table-light">
                    <tr>
                        <th scope="col">STT</th>
                        <th>Mã đơn hàng</th>
                        <th>Khách hàng</th>
                        <th>Loại giao nhận</th>
                        <th>Hình thức</th>
                        <th>Nhân viên</th>
                        <th>Thời gian</th>
                        <th>Trạng thái</th>
                        <th class="text-center">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deliveries as $delivery)
                    <tr>
                        <td>{{ $deliveries->firstItem() + $loop->index }}</td>
                        <td><span class="text-dark">{{ $delivery->donHang?->MaDonHang ?: '—' }}</span></td>
                        <td>{{ $delivery->donHang?->khachHang?->HoTen ?: $delivery->nhanVien?->HoTen ?: '—' }}</td>
                        <td>
                            @if($delivery->LoaiGiaoNhan === 'NHAN_DO')
                                <i class="bi bi-box-arrow-in-down me-1"></i>Nhận đồ
                            @else
                                <i class="bi bi-truck me-1"></i>Giao đồ
                            @endif
                        </td>
                        <td>{{ $delivery->HinhThuc }}</td>
                        <td>{{ $delivery->nhanVien?->HoTen ?: 'Chưa phân công' }}</td>
                        <td>
                            {{ $delivery->ThoiGianDuKien?->format('d/m/Y') ?: '—' }}
                            <br><small class="text-muted">{{ $delivery->ThoiGianDuKien?->format('H:i') ?: '—' }}</small>
                        </td>
                        <td>
                            <x-admin.status-badge :status="\App\Enums\DeliveryStatus::parseForLeg($delivery->TrangThai, $delivery->LoaiGiaoNhan)" :enum="\App\Enums\DeliveryStatus::class" />
                        </td>
                        <td class="text-center">
                            <div class="d-flex gap-2 justify-content-center">
                                @can('deliveries.view')
                                    <a href="{{ route('deliveries.show', $delivery) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a>
                                @endcan
                                @can('deliveries.edit')
                                    <a href="{{ route('deliveries.edit', $delivery) }}" class="btn btn-order-action edit" title="Sửa"><i class="bi bi-pencil"></i></a>
                                @endcan
                                @can('deliveries.delete')
                                    <form action="{{ route('deliveries.destroy', $delivery) }}" method="POST" class="d-inline" id="deleteDeliveryForm_{{ $delivery->GiaoNhanID }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-order-action delete" title="Xóa"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">Chưa có dữ liệu giao nhận</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<x-admin.pagination :paginator="$deliveries" label="giao nhận" />

<div class="card mt-4">
    <div class="card-header bg-white">
        <h2 class="h5 mb-1">Chứng từ nhận/trả từ Booking</h2>
        <p class="small text-muted mb-0">Kế hoạch và phiếu nhận thuộc BookingGiaoNhan; đây không phải phiếu giao nhận vận hành.</p>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle table-custom mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">STT</th>
                        <th>Mã Booking</th>
                        <th>Khách hàng</th>
                        <th>Loại chứng từ</th>
                        <th>Hình thức</th>
                        <th>Nhân viên</th>
                        <th>Thời gian</th>
                        <th>Trạng thái</th>
                        <th>Đơn hàng</th>
                        <th class="text-center">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bookingDocuments as $bookingDocument)
                        @php
                            $bookingRecord = $bookingDocument->booking;
                            $linkedOrder = $bookingDocument->donHang ?? $bookingRecord?->donHangs->first();
                            $isIntake = $bookingDocument->LoaiGiaoNhan === 'NHAN_DO';
                            $isReceived = $isIntake && $bookingDocument->TrangThai === 'Đã nhận';
                        @endphp
                        <tr>
                            <td>{{ $bookingDocuments->firstItem() + $loop->index }}</td>
                            <td>{{ $bookingRecord?->MaBooking ?: '—' }}</td>
                            <td>{{ $bookingRecord?->khachHang?->HoTen ?: '—' }}</td>
                            <td>
                                <span class="badge {{ $isIntake ? 'bg-primary-subtle text-primary-emphasis border border-primary' : 'bg-info-subtle text-info-emphasis border border-info' }}">
                                    {{ $isIntake ? 'Booking · Nhận đồ' : 'Booking · Kế hoạch trả' }}
                                </span>
                            </td>
                            <td>{{ $bookingDocument->HinhThuc ?: '—' }}</td>
                            <td>{{ $bookingDocument->nhanVien?->HoTen ?: 'Chưa phân công' }}</td>
                            <td>
                                {{ $bookingDocument->ThoiGianDuKien?->format('d/m/Y') ?: '—' }}
                                <br><small class="text-muted">{{ $bookingDocument->ThoiGianDuKien?->format('H:i') ?: '—' }}</small>
                            </td>
                            <td>
                                @if($isReceived)
                                    <span class="badge bg-success-subtle text-success-emphasis border border-success">Đã nhận đồ</span>
                                @else
                                    <x-admin.status-badge :status="\App\Enums\DeliveryStatus::parseForLeg($bookingDocument->TrangThai, $bookingDocument->LoaiGiaoNhan)" :enum="\App\Enums\DeliveryStatus::class" />
                                @endif
                            </td>
                            <td>
                                @if($linkedOrder)
                                    @can('orders.view')
                                        <a href="{{ route('orders.show', $linkedOrder) }}">{{ $linkedOrder->MaDonHang }}</a>
                                    @else
                                        {{ $linkedOrder->MaDonHang }}
                                    @endcan
                                @else
                                    <span class="text-muted">Chưa tạo đơn</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @can('deliveries.view')
                                    <a href="{{ route('deliveries.booking-documents.show', $bookingDocument) }}"
                                       class="btn btn-order-action view"
                                       title="Xem chứng từ Booking"
                                       aria-label="Xem chứng từ Booking {{ $bookingRecord?->MaBooking ?: $bookingDocument->BookingGiaoNhanID }}">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-muted py-4">Không có chứng từ Booking phù hợp.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<x-admin.pagination :paginator="$bookingDocuments" label="chứng từ Booking" />
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[id^="deleteDeliveryForm_"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                if (typeof Swal === 'undefined') {
                    if (confirm('Bạn có chắc muốn xóa?')) {
                        form.submit();
                    }
                    return;
                }

                Swal.fire({
                    title: 'Xóa lịch giao nhận?',
                    text: 'Hành động này không thể hoàn tác.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Xóa',
                    cancelButtonText: 'Hủy'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });
    });
</script>
@endpush