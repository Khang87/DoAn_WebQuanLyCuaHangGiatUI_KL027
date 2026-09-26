@extends('layouts.app')

@section('title', 'Thống kê đơn hàng - Sky Laundry')
@section('page-title', 'Thống kê đơn hàng')

@section('content')
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Đơn hàng theo trạng thái</h5>
        <a href="{{ route('reports.index') }}" class="text-decoration-none text-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr><th>Trạng thái</th><th class="text-end">Số lượng</th><th class="text-end">Tỷ lệ</th></tr>
                </thead>
                <tbody>
                    @php
                        $statusLabels = [
                            'pending' => 'Chờ tiếp nhận',
                            'received' => 'Đã nhận đồ',
                            'sorting' => 'Đang phân loại',
                            'processing' => 'Đang giặt / Xử lý',
                            'washed' => 'Đã giặt xong',
                            'delivering' => 'Đang giao đồ',
                            'completed' => 'Hoàn thành',
                            'cancelled' => 'Đã hủy',
                        ];
                    @endphp
                    @foreach($orderStatusCounts as $status => $count)
                    <tr>
                        <td>
                            @if($status === 'completed')
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill"><i class="fas fa-check-circle me-1"></i>{{ $statusLabels[$status] ?? $status }}</span>
                            @elseif($status === 'cancelled')
                                <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill"><i class="fas fa-x-circle me-1"></i>{{ $statusLabels[$status] ?? $status }}</span>
                            @elseif($status === 'pending')
                                <span class="badge bg-warning-subtle text-warning border border-warning px-3 py-2 rounded-pill"><i class="fas fa-hourglass me-1"></i>{{ $statusLabels[$status] ?? $status }}</span>
                            @elseif(in_array($status, ['received', 'sorting', 'delivering', 'processing', 'washed']))
                                <span class="badge bg-info-subtle text-info border border-info px-3 py-2 rounded-pill"><i class="fas fa-cog me-1"></i>{{ $statusLabels[$status] ?? $status }}</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary px-3 py-2 rounded-pill">{{ $statusLabels[$status] ?? $status }}</span>
                            @endif
                        </td>
                        <td class="text-end">{{ number_format($count) }}</td>
                        <td class="text-end">{{ $count > 0 ? round(($count / array_sum($orderStatusCounts)) * 100, 1) : 0 }}%</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Đơn hàng theo dịch vụ</h5>
        <a href="{{ route('reports.index') }}" class="text-decoration-none text-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom mb-0">
                <thead>
                    <tr><th>Dịch vụ</th><th class="text-end">Số đơn</th></tr>
                </thead>
                <tbody>
                    @foreach($serviceCounts as $serviceId => $count)
                    <tr>
                        <td>{{ $services->firstWhere('id', $serviceId)?->name ?? 'Không rõ' }}</td>
                        <td class="text-end fw-semibold">{{ number_format($count) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
