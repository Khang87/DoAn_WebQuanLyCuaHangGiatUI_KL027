<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Quản lý cửa Hàng giặt Ủi')</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('assets/images/favicon.ico') }}">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="{{ asset('assets/libs/bootstrap/css/bootstrap.min.css') }}">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="{{ asset('assets/libs/bootstrap-icons/bootstrap-icons.css') }}">

    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="{{ asset('assets/css/laundry.css') }}">

    @stack('styles')
</head>

<body>
    @php
        $authUser = Auth::user();
        $authAvatarUrl = $authUser ? $authUser->avatar_url : asset('assets/images/user_1.jpg');
        $authName = $authUser?->name ?? 'Quản lý';
    @endphp

    <!-- Sidebar -->
    <div class="sidebar-wrapper" id="sidebar">
        <!-- Brand Logo -->
        <a href="{{ route('dashboard') }}" class="sidebar-brand">
            <img src="{{ asset('assets/images/icon_maygiat.png') }}" alt="Logo" style="width: 28px; height: 28px; object-fit: contain;">
            <span>Sky Laundry</span>
        </a>

        <!-- Navigation Menu -->
        <div class="flex-grow-1 overflow-y-auto">
            <!-- Menu chính -->
            <div class="sidebar-menu-section">
                <div class="sidebar-menu-title">Menu chính</div>
                <ul class="sidebar-menu-list">
                    <li class="sidebar-menu-item">
                        <a href="{{ route('dashboard') }}" class="sidebar-menu-link {{ request()->routeIs('dashboard') || request()->routeIs('admin.dashboard') || request()->routeIs('staff.dashboard') ? 'active' : '' }}">
                            <i class="bi bi-grid-fill"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Quan ly - Chung (Staff & Admin) -->
            <div class="sidebar-menu-section">
                <div class="sidebar-menu-title">Quản lý</div>
                <ul class="sidebar-menu-list">
                    <li class="sidebar-menu-item">
                        <a href="{{ route('customers.index') }}" class="sidebar-menu-link {{ request()->routeIs('customers.*') ? 'active' : '' }}">
                            <i class="bi bi-people"></i>
                            <span>Khách hàng</span>
                        </a>
                    </li>
                    @if(auth()->user()?->isStaff())
                    <li class="sidebar-menu-item">
                        <a href="{{ route('orders.index') }}" class="sidebar-menu-link {{ request()->routeIs('orders.*') ? 'active' : '' }}">
                            <i class="bi bi-receipt"></i>
                            <span>Đơn hàng</span>
                        </a>
                    </li>
                    @endif
                    @if(auth()->user()?->isManager())
                    <li class="sidebar-menu-item">
                        <a href="{{ route('service-categories.index') }}" class="sidebar-menu-link {{ request()->routeIs('service-categories.*') ? 'active' : '' }}">
                            <i class="bi bi-folder-fill"></i>
                            <span>Danh mục DV</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('services.index') }}" class="sidebar-menu-link {{ request()->routeIs('services.*') ? 'active' : '' }}">
                            <i class="bi bi-briefcase"></i>
                            <span>Dịch vụ</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('laundry-categories.index') }}" class="sidebar-menu-link {{ request()->routeIs('laundry-categories.*') ? 'active' : '' }}">
                            <i class="bi bi-tags"></i>
                            <span>Danh mục loại đồ</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('garments.index') }}" class="sidebar-menu-link {{ request()->routeIs('garments.*') ? 'active' : '' }}">
                            <i class="fa-solid fa-shirt"></i>
                            <span>Loại đồ giặt</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('pricings.index') }}" class="sidebar-menu-link {{ request()->routeIs('pricings.*') ? 'active' : '' }}">
                            <i class="bi bi-currency-dollar"></i>
                            <span>Bảng giá</span>
                        </a>
                    </li>
                    @endif
                </ul>
            </div>

            <!-- Giao nhan & Thanh toan (Staff & Admin) -->
            <div class="sidebar-menu-section">
                <div class="sidebar-menu-title">Giao nhận & Thanh toán</div>
                <ul class="sidebar-menu-list">
                    <li class="sidebar-menu-item">
                        <a href="{{ route('deliveries.index') }}" class="sidebar-menu-link {{ request()->routeIs('deliveries.*') ? 'active' : '' }}">
                            <i class="bi bi-truck"></i>
                            <span>Giao nhận</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('reviews.index') }}" class="sidebar-menu-link {{ request()->routeIs('reviews.*') ? 'active' : '' }}">
                            <i class="fas fa-star"></i>
                            <span>Đánh giá</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('bookings.index') }}" class="sidebar-menu-link {{ request()->routeIs('bookings.*') ? 'active' : '' }}">
                            <i class="bi bi-calendar-check"></i>
                            <span>Đặt lịch</span>
                        </a>
                    </li>
                    @if(auth()->user()?->isManager())
                    <li class="sidebar-menu-item">
                        <a href="{{ route('payments.index') }}" class="sidebar-menu-link {{ request()->routeIs('payments.*') ? 'active' : '' }}">
                            <i class="bi bi-credit-card"></i>
                            <span>Thanh toán</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('invoices.index') }}" class="sidebar-menu-link {{ request()->routeIs('invoices.*') ? 'active' : '' }}">
                            <i class="bi bi-file-earmark-text"></i>
                              <span>Hóa đơn</span>
                        </a>
                    </li>
                    @endif
                </ul>
            </div>

            @if(auth()->user()?->isManager())
            <!-- Khuyen mai & Bao cao (Chi quan ly) -->
            <div class="sidebar-menu-section">
                <div class="sidebar-menu-title">Khuyến mãi & Báo cáo</div>
                <ul class="sidebar-menu-list">
                    <li class="sidebar-menu-item">
                        <a href="{{ route('promotions.index') }}" class="sidebar-menu-link {{ request()->routeIs('promotions.*') ? 'active' : '' }}">
                            <i class="bi bi-gift"></i>
                            <span>Khuyến mãi</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('reports.index') }}" class="sidebar-menu-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                            <i class="bi bi-graph-up-arrow"></i>
                            <span>Báo cáo & Thống kê</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- He thong (Chi quan ly) -->
            <div class="sidebar-menu-section">
                <div class="sidebar-menu-title">Hệ thống</div>
                <ul class="sidebar-menu-list">
                    <li class="sidebar-menu-item">
                        <a href="{{ route('accounts.index') }}" class="sidebar-menu-link {{ request()->routeIs('accounts.*') ? 'active' : '' }}">
                            <i class="bi bi-person-gear"></i>
                            <span>Tài khoản</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('notifications.index') }}" class="sidebar-menu-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}">
                            <i class="bi bi-bell"></i>
                            <span>Thông báo</span>
                        </a>
                    </li>
                    @can('roles.manage')
                    <li class="sidebar-menu-item">
                        <a href="{{ route('roles.index') }}" class="sidebar-menu-link {{ request()->routeIs('roles.*') ? 'active' : '' }}">
                            <i class="bi bi-shield-lock"></i>
                            <span>Quản lý phân quyền</span>
                        </a>
                    </li>
                    @endcan
                </ul>
            </div>
            @endif
        </div>

        <!-- Sidebar Profile -->
        <div class="sidebar-profile">
            <img src="{{ $authAvatarUrl }}" alt="{{ $authName }}" class="sidebar-profile-img"
                onerror="this.src='{{ asset('assets/images/user_1.jpg') }}'">
            <div class="sidebar-profile-info">
                <div class="sidebar-profile-name">{{ $authName }}</div>
                <div class="sidebar-profile-email">{{ auth()->user()->email ?? 'quanly@email.com' }}</div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-wrapper">
        <!-- Top Navbar -->
        <header class="navbar-custom">
            <div class="navbar-left">
                <button class="sidebar-toggle-btn" id="sidebar-toggle" aria-label="Toggle Navigation">
                    <i class="bi bi-list"></i>
                </button>
                <h1 class="page-title">@yield('page-title', 'Dashboard')</h1>
            </div>

            <div class="navbar-actions">
                <!-- Notifications -->
                <div class="dropdown">
                    <button class="navbar-action-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-bell"></i>
                        <span class="navbar-action-badge"></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end notification-dropdown">
                        <div class="p-3 border-bottom">
                            <h6 class="mb-0">Thông báo</h6>
                        </div>
                        <div class="p-2" style="max-height: 300px; overflow-y: auto;">
                            <a href="#" class="dropdown-item py-2">
                                <div class="d-flex align-items-center">
                                    <div class="notification-icon bg-success text-white me-3">
                                        <i class="bi bi-check-lg"></i>
                                    </div>
                                    <div>
                                        <p class="mb-1 small">Đơn hàng #1234 đã hoàn thành</p>
                                        <small class="text-muted">2 phút trước</small>
                                    </div>
                                </div>
                            </a>
                            <a href="#" class="dropdown-item py-2">
                                <div class="d-flex align-items-center">
                                    <div class="notification-icon bg-primary text-white me-3">
                                        <i class="bi bi-person-plus"></i>
                                    </div>
                                    <div>
                                        <p class="mb-1 small">Khách hàng mới đăng ký</p>
                                        <small class="text-muted">15 phút trước</small>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                @if(auth()->check() && (auth()->user()->isManager() || auth()->user()->isStaff()))
                <div class="dropdown">
                    <button class="navbar-action-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Lối tắt nhanh">
                        <i class="bi bi-lightning-charge"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end" style="min-width: 220px;">
                        <div class="p-2 border-bottom">
                            <h6 class="mb-0"><i class="bi bi-lightning-charge me-1"></i>Lối tắt nhanh</h6>
                        </div>
                        @can('orders.create')
                        <a class="dropdown-item py-2" href="{{ route('orders.create') }}">
                            <i class="bi bi-plus-circle me-2"></i>Tạo đơn hàng mới
                        </a>
                        @endcan
                        @can('customers.create')
                        <a class="dropdown-item py-2" href="{{ route('customers.create') }}">
                            <i class="bi bi-person-plus me-2"></i>Tạo khách hàng mới
                        </a>
                        @endcan
                        @can('invoices.create')
                        <a class="dropdown-item py-2" href="{{ route('invoices.create') }}">
                            <i class="bi bi-receipt me-2"></i>Lập hóa đơn
                        </a>
                        @endcan
                        @can('services.create')
                        <a class="dropdown-item py-2" href="{{ route('services.create') }}">
                            <i class="bi bi-plus-lg me-2"></i>Thêm dịch vụ
                        </a>
                        @endcan
                        @can('promotions.create')
                        <a class="dropdown-item py-2" href="{{ route('promotions.create') }}">
                            <i class="bi bi-gift me-2"></i>Tạo khuyến mãi
                        </a>
                        @endcan
                        <a class="dropdown-item py-2" href="{{ route('deliveries.create') }}">
                            <i class="bi bi-truck me-2"></i>Tạo lịch giao nhận
                        </a>
                    </div>
                </div>
                @endif

                <!-- User Menu -->
                <div class="dropdown">
                    <button class="navbar-action-btn dropdown-toggle user-profile-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="{{ $authName }}">
                        <img src="{{ $authAvatarUrl }}" alt="{{ $authName }}" class="rounded-circle border avatar-cover"
                            onerror="this.src='{{ asset('assets/images/user_1.jpg') }}'">
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('profile') }}"><i class="bi bi-person me-2"></i> Hồ sơ</a></li>
                        @if(auth()->user()->isManager())
                        <li><a class="dropdown-item" href="{{ route('settings') }}"><i class="bi bi-gear me-2"></i> Cài đặt</a></li>
                        @endif
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="bi bi-box-arrow-right me-2"></i> Đăng xuất
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <div class="content-wrapper">
            @yield('content')
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="{{ asset('assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

    <!-- Flash Messages -->
    @if(session('success') || session('error'))
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Thành công!',
                text: @json(session('success')),
                timer: 2500,
                showConfirmButton: false
            });
            @endif

            @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: 'Không có quyền truy cập!',
                text: @json(session('error')),
                timer: 3500,
                showConfirmButton: false
            });
            @endif
        });
    </script>
    @endif

    <!-- Custom JS -->
    <script>
        // Sidebar Toggle
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('show');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('sidebar-toggle');
            
            if (window.innerWidth <= 991) {
                if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });
    </script>

    <!-- Service-Category Icon Preview (lightweight, no select replacement) -->
    <script src="{{ asset('assets/js/select-icon.js') }}"></script>

    @stack('scripts')
</body>

</html>
