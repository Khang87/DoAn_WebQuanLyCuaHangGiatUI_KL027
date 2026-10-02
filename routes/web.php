<?php

use App\Http\Controllers\Admin\BangGiaController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\ChiTietDonHangController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\DanhGiaController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DichVuController;
use App\Http\Controllers\Admin\DonHangController;
use App\Http\Controllers\Admin\GarmentConditionController;
use App\Http\Controllers\Admin\GiaoNhanController;
use App\Http\Controllers\Admin\HoaDonController;
use App\Http\Controllers\Admin\KhachHangController;
use App\Http\Controllers\Admin\KhuyenMaiController;
use App\Http\Controllers\Admin\LoaiDichVuController;
use App\Http\Controllers\Admin\LoaiDoGiatController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Admin\SystemLogController;
use App\Http\Controllers\Admin\TaiKhoanController;
use App\Http\Controllers\Admin\ThanhToanController;
use App\Http\Controllers\Admin\ThongBaoController;
use App\Http\Controllers\Admin\VaiTroController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Staff\DashboardController as StaffDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| assigned to the "web" middleware group. Make something great!
|
*/

// Authentication Routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Protected Routes
Route::middleware(['auth', 'reject.customer'])->group(function () {

    // Dashboard redirect based on role
    Route::get('/dashboard', function () {
        return auth()->user()->isAdmin()
            ? redirect()->route('admin.dashboard')
            : redirect()->route('staff.dashboard');
    })->name('dashboard');

    // ===== ADMIN DASHBOARD (Manager) =====
    Route::middleware(['role:manager|admin'])->group(function () {
        Route::get('/admin/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::post('/admin/dashboard/collect-cash-payment/{invoice}', [DashboardController::class, 'collectCashPayment'])->name('admin.dashboard.collect-cash-payment');
        Route::get('/admin/dashboard/revenue-chart', [DashboardController::class, 'getRevenueChartData'])->name('admin.dashboard.revenue-chart');
        Route::get('/admin/system-logs', [SystemLogController::class, 'index'])
            ->middleware('role:admin')
            ->name('admin.system-logs.index');
    });

    Route::middleware(['role:manager|admin|staff|employee'])->group(function () {
        Route::get('/admin/messages', [MessageController::class, 'index'])->name('admin.messages.index');
        Route::post('/admin/messages', [MessageController::class, 'store'])->name('admin.messages.store');
    });

    // ===== STAFF DASHBOARD (Employee) =====
    Route::middleware(['role:staff|employee'])->group(function () {
        Route::get('/staff/dashboard', [StaffDashboardController::class, 'index'])->name('staff.dashboard');
        Route::patch('/staff/dashboard/orders/{order}/status', [StaffDashboardController::class, 'updateOrderStatus'])->name('staff.dashboard.update-order-status');
    });

    // Customers Management (Staff & Admin)
    Route::resource('customers', KhachHangController::class)
        ->middlewareFor(['index', 'show'], 'permission:customers.view')
        ->middlewareFor(['create', 'store'], 'permission:customers.create')
        ->middlewareFor(['edit', 'update'], 'permission:customers.edit')
        ->middlewareFor('destroy', 'permission:customers.delete');

    // Orders Management (Staff & Admin)
    // Mỗi route ÁP DỤNG middleware permission riêng để tránh bypass: middleware
    // gốc dùng "|" (OR) nên user chỉ cần có 1 trong các quyền là truy cập được
    // tất cả route (ví dụ: chỉ có orders.view vẫn truy cập được orders.create).
    // Route "/orders/create" phải được đăng ký TRƯỚC "/orders/{order}" để
    // Laravel không nhầm "/orders/create" là tham số {order} = "create".
    Route::middleware('permission:orders.create')->group(function () {
        Route::get('/orders/create', [DonHangController::class, 'create'])->name('orders.create');
        Route::post('/orders', [DonHangController::class, 'store'])->name('orders.store');
    });

    Route::middleware('permission:orders.view')->group(function () {
        Route::get('/orders', [DonHangController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [DonHangController::class, 'show'])->name('orders.show');
    });

    Route::middleware('permission:orders.edit')->group(function () {
        Route::get('/orders/{order}/edit', [DonHangController::class, 'edit'])->name('orders.edit');
        Route::match(['put', 'patch'], '/orders/{order}', [DonHangController::class, 'update'])->name('orders.update');
    });

    Route::middleware('permission:orders.delete')->group(function () {
        Route::delete('/orders/{order}', [DonHangController::class, 'destroy'])->name('orders.destroy');
    });

    Route::resource('order-items', ChiTietDonHangController::class)
        ->middlewareFor(['index', 'show'], 'permission:orders.view')
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:orders.edit');

    // Delivery Management (Staff & Admin)
    Route::resource('deliveries', GiaoNhanController::class)
        ->middlewareFor(['index', 'show'], 'permission:deliveries.view')
        ->middlewareFor(['create', 'store'], 'permission:deliveries.create')
        ->middlewareFor(['edit', 'update'], 'permission:deliveries.edit')
        ->middlewareFor('destroy', 'permission:deliveries.delete');

    // Booking Management (Staff & Admin)
    Route::resource('bookings', BookingController::class)->except(['create', 'store'])
        ->middlewareFor(['index', 'show'], 'permission:bookings.view')
        ->middlewareFor(['edit', 'update'], 'permission:bookings.edit')
        ->middlewareFor('destroy', 'permission:bookings.delete');
    Route::post('bookings/{booking}/confirm', [BookingController::class, 'confirm'])
        ->middleware('permission:bookings.confirm')
        ->name('bookings.confirm');

    // Payments Management (chỉ Quản lý / Admin — tiền nặng)
    Route::middleware(['role:manager|admin'])->group(function () {
        Route::resource('payments', ThanhToanController::class)
            ->middlewareFor(['index', 'show'], 'permission:payments.view')
            ->middlewareFor(['create', 'store'], 'permission:payments.create')
            ->middlewareFor(['edit', 'update'], 'permission:payments.edit')
            ->middlewareFor('destroy', 'permission:payments.delete');

        // Invoices Management (chỉ Quản lý / Admin — quyết toán tài chính)
        Route::get('invoices/export', [HoaDonController::class, 'export'])->middleware('permission:invoices.view')->name('invoices.export');
        Route::get('invoices/{invoice}/export-excel', [HoaDonController::class, 'exportExcel'])->middleware('permission:invoices.view')->name('invoices.export-excel');
        Route::post('invoices/{invoice}/status', [HoaDonController::class, 'updateStatus'])->middleware('permission:invoices.update_status')->name('invoices.update-status');
        Route::resource('invoices', HoaDonController::class)
            ->middlewareFor(['index', 'show'], 'permission:invoices.view')
            ->middlewareFor(['create', 'store'], 'permission:invoices.create')
            ->middlewareFor(['edit', 'update'], 'permission:invoices.edit')
            ->middlewareFor('destroy', 'permission:invoices.delete');
    });

    // Reviews Management (Staff & Admin)
    Route::resource('reviews', DanhGiaController::class)->only(['index', 'show'])->middleware('permission:reviews.view');
    Route::patch('reviews/{review}/respond', [DanhGiaController::class, 'respond'])->middleware('permission:reviews.respond')->name('reviews.respond');

    // ===== ADMIN ONLY ROUTES =====
    Route::middleware(['role:manager|admin'])->group(function () {
        Route::patch('reviews/{review}/toggle', [DanhGiaController::class, 'toggleStatus'])->middleware('permission:reviews.toggle')->name('reviews.toggle');

        // Services Management
        Route::resource('service-categories', LoaiDichVuController::class)
            ->middlewareFor(['index', 'show'], 'permission:service_categories.view')
            ->middlewareFor(['create', 'store'], 'permission:service_categories.create')
            ->middlewareFor(['edit', 'update'], 'permission:service_categories.edit')
            ->middlewareFor('destroy', 'permission:service_categories.delete');
        Route::resource('services', DichVuController::class)
            ->middlewareFor(['index', 'show'], 'permission:services.view')
            ->middlewareFor(['create', 'store'], 'permission:services.create')
            ->middlewareFor(['edit', 'update'], 'permission:services.edit')
            ->middlewareFor('destroy', 'permission:services.delete');

        Route::resource('garment-conditions', GarmentConditionController::class)
            ->middlewareFor(['index', 'show'], 'permission:garment_conditions.view')
            ->middlewareFor(['create', 'store'], 'permission:garment_conditions.create')
            ->middlewareFor(['edit', 'update'], 'permission:garment_conditions.edit')
            ->middlewareFor('destroy', 'permission:garment_conditions.delete');
        // Pricing Management
        Route::resource('pricings', BangGiaController::class)
            ->middlewareFor(['index', 'show'], 'permission:pricings.view')
            ->middlewareFor(['create', 'store'], 'permission:pricings.create')
            ->middlewareFor(['edit', 'update'], 'permission:pricings.edit')
            ->middlewareFor('destroy', 'permission:pricings.delete');

        // Promotions & Coupons Management
        Route::resource('promotions', KhuyenMaiController::class)
            ->middlewareFor(['index', 'show'], 'permission:promotions.view')
            ->middlewareFor(['create', 'store'], 'permission:promotions.create')
            ->middlewareFor(['edit', 'update'], 'permission:promotions.edit')
            ->middlewareFor('destroy', 'permission:promotions.delete');
        Route::resource('coupons', CouponController::class)
            ->middlewareFor(['index', 'show'], 'permission:coupons.view')
            ->middlewareFor(['create', 'store'], 'permission:coupons.create')
            ->middlewareFor(['edit', 'update'], 'permission:coupons.edit')
            ->middlewareFor('destroy', 'permission:coupons.delete');

        // Reports (Owner & Manager only)
        Route::prefix('reports')->name('reports.')->middleware(['role:manager|admin', 'permission:reports.view'])->group(function () {
            Route::get('/export', [ReportsController::class, 'export'])->name('export');
            Route::get('/', [ReportsController::class, 'index'])->name('index');
        });

        // Accounts Management
        Route::resource('accounts', TaiKhoanController::class)
            ->middlewareFor(['index', 'show'], 'permission:accounts.view')
            ->middlewareFor(['create', 'store'], 'permission:accounts.create')
            ->middlewareFor(['edit', 'update'], 'permission:accounts.edit')
            ->middlewareFor('destroy', 'permission:accounts.delete');
        Route::post('accounts/{taiKhoanId}/roles', [TaiKhoanController::class, 'updateRole'])
            ->middleware('permission:accounts.edit')
            ->name('accounts.update-role');
        Route::post('accounts/{account}/toggle-status', [TaiKhoanController::class, 'toggleStatus'])->middleware('permission:accounts.edit')->name('accounts.toggle-status');
        Route::post('accounts/{account}/reset-password', [TaiKhoanController::class, 'resetPassword'])->middleware('permission:accounts.reset_password')->name('accounts.reset-password');

        // Notifications Management
        Route::resource('notifications', ThongBaoController::class)
            ->middlewareFor(['index', 'show'], 'permission:notifications.view')
            ->middlewareFor(['create', 'store'], 'permission:notifications.create')
            ->middlewareFor(['edit', 'update'], 'permission:notifications.edit')
            ->middlewareFor('destroy', 'permission:notifications.delete');
        Route::patch('notifications/{notification}/mark-read', [ThongBaoController::class, 'markAsRead'])
            ->middleware('permission:notifications.edit')
            ->name('notifications.mark-read');
        Route::post('notifications/mark-all-read', [ThongBaoController::class, 'markAllAsRead'])
            ->middleware('permission:notifications.edit')
            ->name('notifications.mark-all-read');

        // ===== PHÂN QUYỀN ĐỘNG (RBAC) =====
        // Ma trận vai trò - quyền hạn, chỉ Chủ cửa hàng được phân quyền.
        Route::get('roles', [VaiTroController::class, 'index'])->name('roles.index');
        Route::put('roles/permissions', [VaiTroController::class, 'update'])->name('roles.update');

        // Services Management - add toggle status
        // Route::post('services/{service}/toggle-status', [DichVuController::class, 'toggleStatus'])->name('services.toggle-status');
        Route::post('service-categories/{service_category}/toggle-status', [DichVuController::class, 'toggleStatus'])->middleware('permission:service_categories.edit')->name('service-categories.toggle-status');
    });

    // Loại đồ giặt: chủ cửa hàng hoặc nhân viên có quyền theo từng thao tác.
    Route::middleware('role:admin|staff|employee')->group(function () {
        Route::resource('loai-do-giat', LoaiDoGiatController::class)
            ->names('loaidogiat')
            ->parameters(['loai-do-giat' => 'loai_do_giat'])
            ->middlewareFor(['index', 'show'], 'permission:garment_categories.view')
            ->middlewareFor(['create', 'store'], 'permission:garment_categories.create')
            ->middlewareFor(['edit', 'update'], 'permission:garment_categories.edit')
            ->middlewareFor('destroy', 'permission:garment_categories.delete');
    });

    foreach (['garments', 'garment-categories', 'laundry-categories'] as $legacyPath) {
        Route::get($legacyPath, fn () => redirect()->route('loaidogiat.index'))
            ->middleware('permission:garment_categories.view')
            ->name($legacyPath.'.legacy-redirect');
    }

    // User Profile (Staff & Admin) — ai cũng tự sửa được hồ sơ của mình
    Route::get('/profile', [TaiKhoanController::class, 'profile'])->name('profile');
    Route::put('/profile', [TaiKhoanController::class, 'updateProfile'])->name('profile.update');
    Route::post('/profile/avatar', [TaiKhoanController::class, 'updateAvatar'])->name('profile.avatar');
    Route::post('/profile/change-password', [TaiKhoanController::class, 'changePassword'])->name('profile.change-password');

    // ===== CẤU HÌNH HỆ THỐNG (chỉ Quản lý / Admin) =====
    Route::middleware(['role:manager|admin'])->group(function () {
        Route::get('/settings', [TaiKhoanController::class, 'settings'])->name('settings');
    });
});

// Default route redirect to login or dashboard
Route::get('/', function () {
    return redirect()->route('login');
});
