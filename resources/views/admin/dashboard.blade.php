@extends('layouts.app')

@section('title', 'Dashboard - Quản Lý Cửa Hàng Giặt Ủi')
@section('page-title', 'Dashboard')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/libs/apexcharts/apexcharts.css') }}">
@endpush

@section('content')
<!-- Stats Cards Row -->
<div class="row g-4 mb-4">
    <!-- Tổng Đơn Hàng -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value">{{ $totalOrders }}</div>
                        <div class="stat-label">Tổng Đơn Hàng</div>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-receipt"></i>
                    </div>
                </div>
                <div class="mt-3">
                        <small class="text-muted"><i class="bi bi-database"></i> Dữ liệu thực tế</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Doanh Thu -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value">{{ number_format($revenue / 1000000, 1) }}M</div>
                        <div class="stat-label">Doanh Thu (VNĐ)</div>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-currency-dollar"></i>
                    </div>
                </div>
                <div class="mt-3">
                        <small class="text-muted"><i class="bi bi-database"></i> Từ đơn hàng hiện có</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Khách Hàng Mới -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value">{{ $newCustomers }}</div>
                        <div class="stat-label">Khách Hàng Mới</div>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
                <div class="mt-3">
                        <small class="text-muted"><i class="bi bi-calendar3"></i> Trong 30 ngày qua</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Đơn Chờ Xử Lý -->
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="stat-value">{{ $pendingOrders }}</div>
                        <div class="stat-label">Đơn Chờ Xử Lý</div>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-clock-history"></i>
                    </div>
                </div>
                <div class="mt-3">
                        <small class="text-warning"><i class="bi bi-exclamation-circle"></i> Đơn đang chờ xử lý</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-4 mb-4">
    <!-- Doanh Thu Chart -->
    <div class="col-12 col-xl-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Thống Kê Doanh Thu</h5>
                <select class="form-select form-select-sm" style="width: auto;">
                    <option>7 ngày qua</option>
                    <option selected>30 ngày qua</option>
                    <option>3 tháng qua</option>
                </select>
            </div>
            <div class="card-body">
                <div id="revenue-chart" style="height: 300px;"></div>
            </div>
        </div>
    </div>

    <!-- Trạng Thái Đơn Hàng -->
    <div class="col-12 col-xl-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Trạng Thái Đơn Hàng</h5>
            </div>
            <div class="card-body">
                <div id="status-chart" style="height: 300px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Orders & Recent Customers Row -->
<div class="row g-4">
    <!-- Đơn Hàng Gần Đây -->
    <div class="col-12 col-xl-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Đơn Hàng Gần Đây</h5>
                <a href="{{ route('orders.index') }}" class="btn btn-sm btn-outline-primary">Xem Tất Cả</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table-custom mb-0">
                        <thead>
                            <tr>
                                <th>Mã Đơn</th>
                                <th>Khách Hàng</th>
                                <th>Dịch Vụ</th>
                                <th>Tổng Tiền</th>
                                <th>Trạng Thái</th>
                                <th>Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentOrders as $order)
                            <tr>
                                <td><strong>#{{ $order->code }}</strong></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="{{ asset('assets/images/user.jfif') }}" alt="Ảnh khách hàng" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                                        <span>{{ $order->customer?->name ?: '-' }}</span>
                                    </div>
                                </td>
                                <td>{{ $order->service?->name ?: '-' }}</td>
                                <td><strong>{{ number_format($order->total_amount) }} VNĐ</strong></td>
                                <td><span class="badge-status badge-{{ $order->status === 'completed' ? 'completed' : ($order->status === 'pending' ? 'pending' : 'processing') }}">{{ $order->status }}</span></td>
                                <td><a href="{{ route('orders.show', $order) }}" class="btn btn-order-action view" title="Xem"><i class="bi bi-eye"></i></a></td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Chưa có đơn hàng</td></tr>
                            @endforelse
                            @if(false)
                            <tr>
                                <td><strong>#DH001</strong></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=32&auto=format&fit=crop" 
                                             alt="Avatar" class="rounded-circle me-2" style="width: 32px; height: 32px;">
                                        <span>Nguyễn Văn A</span>
                                    </div>
                                </td>
                                <td>Giặt ủi + Giặt khô</td>
                                <td><strong>250,000 VNĐ</strong></td>
                                <td><span class="badge-status badge-processing">Đang xử lý</span></td>
                                <td>
                                    <a href="#" class="btn btn-sm btn-outline-primary me-1"><i class="bi bi-eye"></i></a>
                                    <a href="#" class="btn btn-sm btn-outline-success"><i class="bi bi-check-lg"></i></a>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>#DH002</strong></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=32&auto=format&fit=crop" 
                                             alt="Avatar" class="rounded-circle me-2" style="width: 32px; height: 32px;">
                                        <span>Trần Thị B</span>
                                    </div>
                                </td>
                                <td>Giặt ủi thường</td>
                                <td><strong>180,000 VNĐ</strong></td>
                                <td><span class="badge-status badge-pending">Chờ xử lý</span></td>
                                <td>
                                    <a href="#" class="btn btn-sm btn-outline-primary me-1"><i class="bi bi-eye"></i></a>
                                    <a href="#" class="btn btn-sm btn-outline-success"><i class="bi bi-check-lg"></i></a>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>#DH003</strong></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?q=80&w=32&auto=format&fit=crop" 
                                             alt="Avatar" class="rounded-circle me-2" style="width: 32px; height: 32px;">
                                        <span>Phạm Thị C</span>
                                    </div>
                                </td>
                                <td>Giặt khô + Ủi</td>
                                <td><strong>320,000 VNĐ</strong></td>
                                <td><span class="badge-status badge-completed">Hoàn thành</span></td>
                                <td>
                                    <a href="#" class="btn btn-sm btn-outline-primary me-1"><i class="bi bi-eye"></i></a>
                                    <a href="#" class="btn btn-sm btn-outline-info"><i class="bi bi-printer"></i></a>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>#DH004</strong></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?q=80&w=32&auto=format&fit=crop" 
                                             alt="Avatar" class="rounded-circle me-2" style="width: 32px; height: 32px;">
                                        <span>Lê Văn D</span>
                                    </div>
                                </td>
                                <td>Giặt ủi + Giặt khô + Ủi</td>
                                <td><strong>450,000 VNĐ</strong></td>
                                <td><span class="badge-status badge-processing">Đang xử lý</span></td>
                                <td>
                                    <a href="#" class="btn btn-sm btn-outline-primary me-1"><i class="bi bi-eye"></i></a>
                                    <a href="#" class="btn btn-sm btn-outline-success"><i class="bi bi-check-lg"></i></a>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>#DH005</strong></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="https://images.unsplash.com/photo-1438761681033-6461ffad8d80?q=80&w=32&auto=format&fit=crop" 
                                             alt="Avatar" class="rounded-circle me-2" style="width: 32px; height: 32px;">
                                        <span>Hoàng Thị E</span>
                                    </div>
                                </td>
                                <td>Giặt ủi thường</td>
                                <td><strong>150,000 VNĐ</strong></td>
                                <td><span class="badge-status badge-completed">Hoàn thành</span></td>
                                <td>
                                    <a href="#" class="btn btn-sm btn-outline-primary me-1"><i class="bi bi-eye"></i></a>
                                    <a href="#" class="btn btn-sm btn-outline-info"><i class="bi bi-printer"></i></a>
                                </td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Khách Hàng Mới -->
    <div class="col-12 col-xl-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Khách Hàng Mới</h5>
                <a href="{{ route('customers.index') }}" class="btn btn-sm btn-outline-primary">Xem Tất Cả</a>
            </div>
            <div class="card-body">
                @forelse($recentCustomers as $customer)
                <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                    <img src="{{ asset('assets/images/user.jfif') }}" alt="Ảnh khách hàng" class="rounded-circle me-3" style="width: 48px; height: 48px; object-fit: cover;">
                    <div class="flex-grow-1">
                        <div class="fw-semibold">{{ $customer->name }}</div>
                        <small class="text-muted">{{ $customer->email ?: 'Chưa có email' }}</small>
                    </div>
                    <span class="customer-type-badge {{ $customer->type === 'VIP' ? 'vip' : ($customer->type === 'Mới' ? 'new' : 'regular') }}">{{ $customer->type }}</span>
                </div>
                @empty
                <p class="text-center text-muted">Chưa có khách hàng</p>
                @endforelse
                @if(false)
                <!-- Customer Item -->
                <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                    <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=48&auto=format&fit=crop" 
                         alt="Avatar" class="rounded-circle me-3" style="width: 48px; height: 48px;">
                    <div class="flex-grow-1">
                        <div class="fw-semibold">Nguyễn Văn A</div>
                        <small class="text-muted">nguyenvana@email.com</small>
                    </div>
                    <span class="badge bg-primary-subtle text-primary">Mới</span>
                </div>

                <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                    <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=48&auto=format&fit=crop" 
                         alt="Avatar" class="rounded-circle me-3" style="width: 48px; height: 48px;">
                    <div class="flex-grow-1">
                        <div class="fw-semibold">Trần Thị B</div>
                        <small class="text-muted">tranthib@email.com</small>
                    </div>
                    <span class="badge bg-primary-subtle text-primary">Mới</span>
                </div>

                <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                    <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?q=80&w=48&auto=format&fit=crop" 
                         alt="Avatar" class="rounded-circle me-3" style="width: 48px; height: 48px;">
                    <div class="flex-grow-1">
                        <div class="fw-semibold">Phạm Thị C</div>
                        <small class="text-muted">phamthic@email.com</small>
                    </div>
                    <span class="badge bg-primary-subtle text-primary">Mới</span>
                </div>

                <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                    <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?q=80&w=48&auto=format&fit=crop" 
                         alt="Avatar" class="rounded-circle me-3" style="width: 48px; height: 48px;">
                    <div class="flex-grow-1">
                        <div class="fw-semibold">Lê Văn D</div>
                        <small class="text-muted">levand@email.com</small>
                    </div>
                    <span class="badge bg-success-subtle text-success">VIP</span>
                </div>

                <div class="d-flex align-items-center">
                    <img src="https://images.unsplash.com/photo-1438761681033-6461ffad8d80?q=80&w=48&auto=format&fit=crop" 
                         alt="Avatar" class="rounded-circle me-3" style="width: 48px; height: 48px;">
                    <div class="flex-grow-1">
                        <div class="fw-semibold">Hoàng Thị E</div>
                        <small class="text-muted">hoangthie@email.com</small>
                    </div>
                    <span class="badge bg-primary-subtle text-primary">Mới</span>
                </div>
                @endif
            </div>
        </div>

        <!-- Khuyến Mãi Đang Áp Dụng -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Khuyến Mãi Đang Áp Dụng</h5>
            </div>
            <div class="card-body">
                @forelse($activePromotions as $promotion)
                <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                    <div class="bg-warning text-white rounded p-2 me-3"><i class="bi bi-percent"></i></div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">{{ $promotion->name }}</div>
                        <small class="text-muted">Mã: {{ $promotion->code }} · HSD: {{ $promotion->expires_at?->format('d/m/Y') ?: 'Không thời hạn' }}</small>
                    </div>
                </div>
                @empty
                <p class="text-center text-muted">Chưa có khuyến mãi đang áp dụng</p>
                @endforelse
                @if(false)
                <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                    <div class="bg-warning text-white rounded p-2 me-3">
                        <i class="bi bi-percent"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">Giảm 20% Đơn Đầu</div>
                        <small class="text-muted">HSD: 30/09/2024</small>
                    </div>
                </div>
                <div class="d-flex align-items-center">
                    <div class="bg-success text-white rounded p-2 me-3">
                        <i class="bi bi-gift"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">Tặng 50K Đơn 500K</div>
                        <small class="text-muted">HSD: 15/10/2024</small>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/libs/apexcharts/apexcharts.min.js') }}"></script>
<script>
    // Revenue Chart
    var revenueOptions = {
        series: [{
            name: 'Doanh thu',
            data: @json($monthlyRevenue->map(fn ($value) => round($value / 1000000, 2))->values())
        }],
        chart: {
            type: 'area',
            height: 300,
            fontFamily: 'Plus Jakarta Sans, sans-serif',
            toolbar: {
                show: false
            }
        },
        colors: ['#60A5FA'],
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.4,
                opacityTo: 0.1,
                stops: [0, 90, 100]
            }
        },
        dataLabels: {
            enabled: false
        },
        stroke: {
            curve: 'smooth',
            width: 2
        },
        xaxis: {
            categories: ['T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'T8', 'T9', 'T10', 'T11', 'T12'],
            labels: {
                style: {
                    colors: '#64748B',
                    fontSize: '12px'
                }
            }
        },
        yaxis: {
            labels: {
                style: {
                    colors: '#64748B',
                    fontSize: '12px'
                },
                formatter: function(value) {
                    return value + 'M';
                }
            }
        },
        tooltip: {
            theme: 'light',
            y: {
                formatter: function(value) {
                    return value + ' triệu VNĐ';
                }
            }
        }
    };
    var revenueChart = new ApexCharts(document.querySelector("#revenue-chart"), revenueOptions);
    revenueChart.render();

    // Status Chart
    var statusOptions = {
        series: @json(array_values($statusCounts)),
        chart: {
            type: 'donut',
            height: 300,
            fontFamily: 'Plus Jakarta Sans, sans-serif'
        },
        colors: ['#F59E0B', '#3B82F6', '#22C55E', '#EF4444'],
        labels: ['Chờ xử lý', 'Đang xử lý', 'Hoàn thành', 'Đã hủy'],
        plotOptions: {
            pie: {
                donut: {
                    size: '65%',
                    labels: {
                        show: true,
                        total: {
                            show: true,
                            label: 'Tổng',
                            fontSize: '16px',
                            fontWeight: 600,
                            color: '#1E293B'
                        }
                    }
                }
            }
        },
        dataLabels: {
            enabled: false
        },
        legend: {
            position: 'bottom',
            fontSize: '13px',
            markers: {
                width: 10,
                height: 10
            }
        }
    };
    var statusChart = new ApexCharts(document.querySelector("#status-chart"), statusOptions);
    statusChart.render();
</script>
@endpush
