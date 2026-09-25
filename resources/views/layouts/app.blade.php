<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Quản Lý Cửa Hàng Giặt Ủi')</title>

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
    <!-- Sidebar -->
    <div class="sidebar-wrapper" id="sidebar">
        <!-- Brand Logo -->
        <a href="{{ route('dashboard') }}" class="sidebar-brand">
            <img src="{{ asset('assets/images/icon_maygiat.png') }}" alt="Logo" style="width: 28px; height: 28px; object-fit: contain;">
            <span>Sky Laundry</span>
        </a>

        <!-- Navigation Menu -->
        <div class="flex-grow-1 overflow-y-auto">
            <!-- Menu Chính -->
            <div class="sidebar-menu-section">
                <div class="sidebar-menu-title">Menu Chính</div>
                <ul class="sidebar-menu-list">
                    <li class="sidebar-menu-item">
                        <a href="{{ route('dashboard') }}" class="sidebar-menu-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <i class="bi bi-grid-fill"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Quản Lý - Chung (Staff & Admin) -->
            <div class="sidebar-menu-section">
                <div class="sidebar-menu-title">Quản Lý</div>
                <ul class="sidebar-menu-list">
                    <li class="sidebar-menu-item">
                        <a href="{{ route('customers.index') }}" class="sidebar-menu-link {{ request()->routeIs('customers.*') ? 'active' : '' }}">
                            <i class="bi bi-people"></i>
                            <span>Khách Hàng</span>
                        </a>
                    </li>
                    @if(auth()->user()?->isStaff())
                    <li class="sidebar-menu-item">
                        <a href="{{ route('orders.index') }}" class="sidebar-menu-link {{ request()->routeIs('orders.*') ? 'active' : '' }}">
                            <i class="bi bi-receipt"></i>
                            <span>Đơn Hàng</span>
                        </a>
                    </li>
                    @endif
                    @if(auth()->user()?->isAdmin())
                    <li class="sidebar-menu-item">
                        <a href="{{ route('service-categories.index') }}" class="sidebar-menu-link {{ request()->routeIs('service-categories.*') ? 'active' : '' }}">
                            <i class="bi bi-folder-fill"></i>
                            <span>Danh mục DV</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('services.index') }}" class="sidebar-menu-link {{ request()->routeIs('services.*') ? 'active' : '' }}">
                            <i class="bi bi-briefcase"></i>
                            <span>Dịch Vụ</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('garments.index') }}" class="sidebar-menu-link {{ request()->routeIs('garments.*') ? 'active' : '' }}">
                            <i class="fa-solid fa-shirt"></i>
                            <span>Loại Đồ Giặt</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('pricings.index') }}" class="sidebar-menu-link {{ request()->routeIs('pricings.*') ? 'active' : '' }}">
                            <i class="bi bi-currency-dollar"></i>
                            <span>Bảng Giá</span>
                        </a>
                    </li>
                    @endif
                </ul>
            </div>

            <!-- Giao Nhận & Thanh Toán (Staff & Admin) -->
            <div class="sidebar-menu-section">
                <div class="sidebar-menu-title">Giao Nhận & Thanh Toán</div>
                <ul class="sidebar-menu-list">
                    <li class="sidebar-menu-item">
                        <a href="{{ route('deliveries.index') }}" class="sidebar-menu-link {{ request()->routeIs('deliveries.*') ? 'active' : '' }}">
                            <i class="bi bi-truck"></i>
                            <span>Giao Nhận</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('bookings.index') }}" class="sidebar-menu-link {{ request()->routeIs('bookings.*') ? 'active' : '' }}">
                            <i class="bi bi-calendar-check"></i>
                            <span>Đặt Lịch</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('payments.index') }}" class="sidebar-menu-link {{ request()->routeIs('payments.*') ? 'active' : '' }}">
                            <i class="bi bi-credit-card"></i>
                            <span>Thanh Toán</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('invoices.index') }}" class="sidebar-menu-link {{ request()->routeIs('invoices.*') ? 'active' : '' }}">
                            <i class="bi bi-file-earmark-text"></i>
                            <span>Hóa Đơn</span>
                        </a>
                    </li>
                </ul>
            </div>

            @if(auth()->user()?->isAdmin())
            <!-- Khuyến Mãi & Báo Cáo (Chỉ Admin) -->
            <div class="sidebar-menu-section">
                <div class="sidebar-menu-title">Khuyến Mãi & Báo Cáo</div>
                <ul class="sidebar-menu-list">
                    <li class="sidebar-menu-item">
                        <a href="{{ route('promotions.index') }}" class="sidebar-menu-link {{ request()->routeIs('promotions.*') ? 'active' : '' }}">
                            <i class="bi bi-gift"></i>
                            <span>Khuyến Mãi</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('reports.index') }}" class="sidebar-menu-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                            <i class="bi bi-bar-chart-line"></i>
                            <span>Báo Cáo</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Hệ Thống (Chỉ Admin) -->
            <div class="sidebar-menu-section">
                <div class="sidebar-menu-title">Hệ Thống</div>
                <ul class="sidebar-menu-list">
                    <li class="sidebar-menu-item">
                        <a href="{{ route('accounts.index') }}" class="sidebar-menu-link {{ request()->routeIs('accounts.*') ? 'active' : '' }}">
                            <i class="bi bi-person-gear"></i>
                            <span>Tài Khoản</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="{{ route('notifications.index') }}" class="sidebar-menu-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}">
                            <i class="bi bi-bell"></i>
                            <span>Thông Báo</span>
                        </a>
                    </li>
                </ul>
            </div>
            @endif
        </div>

        <!-- Sidebar Profile -->
        <div class="sidebar-profile">
            <img src="{{ asset('assets/images/avatar.png') }}" alt="User" class="sidebar-profile-img"
                onerror="this.src='https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=256&auto=format&fit=crop'">
            <div class="sidebar-profile-info">
                <div class="sidebar-profile-name">{{ auth()->user()->name ?? 'Administrator' }}</div>
                <div class="sidebar-profile-email">{{ auth()->user()->email ?? 'admin@email.com' }}</div>
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
                            <h6 class="mb-0">Thông Báo</h6>
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

                <!-- User Menu -->
                @php
                    $authUser = Auth::user();
                    $authAvatar = 'assets/images/user_1.jpg';
                    if ($authUser) {
                        if (!empty($authUser->avatar) && file_exists(public_path($authUser->avatar))) {
                            $authAvatar = $authUser->avatar;
                        } else {
                            $authAvatar = 'assets/images/user_' . (($authUser->id % 8) + 1) . '.jpg';
                        }
                    }
                @endphp
                <div class="dropdown">
                    <button class="navbar-action-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="{{ asset($authAvatar) }}" alt="{{ $authUser->name ?? 'User' }}" class="rounded-circle border" style="width: 32px; height: 32px; object-fit: cover;">
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('profile') }}"><i class="bi bi-person me-2"></i> Hồ Sơ</a></li>
                        <li><a class="dropdown-item" href="{{ route('settings') }}"><i class="bi bi-gear me-2"></i> Cài Đặt</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="bi bi-box-arrow-right me-2"></i> Đăng Xuất
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

    @stack('scripts')
</body>

</html>
