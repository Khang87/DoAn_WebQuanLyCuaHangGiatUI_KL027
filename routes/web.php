<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Staff\DashboardController as StaffDashboardController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\OrderItemController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\ServiceCategoryController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\PricingController;
use App\Http\Controllers\Admin\DeliveryController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\LaundryCategoryController;
use App\Http\Controllers\Admin\GarmentConditionController;
use App\Http\Controllers\Admin\GarmentController;
use App\Http\Controllers\Admin\GarmentCategoryController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\RoleController;

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
    });

    // ===== STAFF DASHBOARD (Employee) =====
    Route::middleware(['role:staff|employee'])->group(function () {
        Route::get('/staff/dashboard', [StaffDashboardController::class, 'index'])->name('staff.dashboard');
        Route::patch('/staff/dashboard/orders/{order}/status', [StaffDashboardController::class, 'updateOrderStatus'])->name('staff.dashboard.update-order-status');
    });

    // Customers Management (Staff & Admin)
    Route::resource('customers', CustomerController::class)->middleware('permission:customers.view| customers.create| customers.edit| customers.delete');

    // Orders Management (Staff & Admin)
    Route::resource('orders', OrderController::class)->middleware('permission:orders.view| orders.create| orders.edit| orders.delete| orders.update_status');
    Route::resource('order-items', OrderItemController::class)->middleware('permission:orders.view| orders.edit');

    // Delivery Management (Staff & Admin)
    Route::resource('deliveries', DeliveryController::class)->middleware('permission:deliveries.view| deliveries.create| deliveries.edit| deliveries.delete');

    // Booking Management (Staff & Admin)
    Route::resource('bookings', BookingController::class)->except(['create', 'store'])
        ->middleware('permission:bookings.view| bookings.edit| bookings.delete');
    Route::post('bookings/{booking}/confirm', [BookingController::class, 'confirm'])
        ->middleware('permission:bookings.confirm')
        ->name('bookings.confirm');

    // Payments Management (chỉ Quản lý / Admin — tiền nặng)
    Route::middleware(['role:manager|admin'])->group(function () {
        Route::resource('payments', PaymentController::class)->middleware('permission:payments.view| payments.create| payments.edit| payments.delete');

        // Invoices Management (chỉ Quản lý / Admin — quyết toán tài chính)
        Route::get('invoices/export', [InvoiceController::class, 'export'])->middleware('permission:invoices.view')->name('invoices.export');
        Route::get('invoices/{invoice}/export-excel', [InvoiceController::class, 'exportExcel'])->middleware('permission:invoices.view')->name('invoices.export-excel');
        Route::post('invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->middleware('permission:invoices.update_status')->name('invoices.update-status');
        Route::resource('invoices', InvoiceController::class)->middleware('permission:invoices.view| invoices.create| invoices.edit| invoices.delete');
    });

    // Reviews Management (Staff & Admin)
    Route::resource('reviews', ReviewController::class)->only(['index', 'show'])->middleware('permission:reviews.view');
    Route::patch('reviews/{review}/respond', [ReviewController::class, 'respond'])->middleware('permission:reviews.respond')->name('reviews.respond');

    // ===== ADMIN ONLY ROUTES =====
    Route::middleware(['role:manager|admin'])->group(function () {
        Route::patch('reviews/{review}/toggle', [ReviewController::class, 'toggleStatus'])->middleware('permission:reviews.toggle')->name('reviews.toggle');

        // Services Management
        Route::resource('service-categories', ServiceCategoryController::class)->middleware('permission:service_categories.view| service_categories.create| service_categories.edit| service_categories.delete');
        Route::resource('services', ServiceController::class)->middleware('permission:services.view| services.create| services.edit| services.delete');

        // Garments Management
        Route::resource('garments', GarmentController::class)->middleware('permission:garments.view| garments.create| garments.edit| garments.delete');
        Route::resource('garment-conditions', GarmentConditionController::class)->middleware('permission:garment_conditions.view| garment_conditions.create| garment_conditions.edit| garment_conditions.delete');
        Route::resource('garment-categories', GarmentCategoryController::class)->middleware('permission:garment_categories.view| garment_categories.create| garment_categories.edit| garment_categories.delete');
        
        // Laundry Categories Management
        Route::resource('laundry-categories', LaundryCategoryController::class)->middleware('permission:laundry_categories.view| laundry_categories.create| laundry_categories.edit| laundry_categories.delete');

        // Pricing Management
        Route::resource('pricings', PricingController::class)->middleware('permission:pricings.view| pricings.create| pricings.edit| pricings.delete');

        // Promotions & Coupons Management
        Route::resource('promotions', PromotionController::class)->middleware('permission:promotions.view| promotions.create| promotions.edit| promotions.delete');
        Route::resource('coupons', CouponController::class)->middleware('permission:coupons.view| coupons.create| coupons.edit| coupons.delete');

        // Reports (Owner & Manager only)
        Route::prefix('reports')->name('reports.')->middleware(['role:manager|admin', 'permission:reports.view'])->group(function () {
            Route::get('/', [ReportsController::class, 'index'])->name('index');
        });

        // Accounts Management
        Route::resource('accounts', AccountController::class)->middleware('permission:accounts.view| accounts.create| accounts.edit| accounts.delete');
        Route::post('accounts/{account}/toggle-status', [AccountController::class, 'toggleStatus'])->middleware('permission:accounts.edit')->name('accounts.toggle-status');
        Route::post('accounts/{account}/reset-password', [AccountController::class, 'resetPassword'])->middleware('permission:accounts.reset_password')->name('accounts.reset-password');

        // Notifications Management
        Route::resource('notifications', NotificationController::class)->middleware('permission:notifications.view| notifications.create| notifications.edit| notifications.delete');

        // ===== PHÂN QUYỀN ĐỘNG (RBAC) =====
        // Ma trận vai trò - quyền hạn, chỉ Chủ cửa hàng được phân quyền.
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::put('roles/permissions', [RoleController::class, 'update'])->name('roles.update');

        // Services Management - add toggle status
        // Route::post('services/{service}/toggle-status', [ServiceController::class, 'toggleStatus'])->name('services.toggle-status');
        Route::post('service-categories/{service_category}/toggle-status', [ServiceController::class, 'toggleStatus'])->middleware('permission:service_categories.edit')->name('service-categories.toggle-status');
    });

    // User Profile (Staff & Admin) — ai cũng tự sửa được hồ sơ của mình
    Route::get('/profile', [AccountController::class, 'profile'])->name('profile');
    Route::put('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');
    Route::post('/profile/avatar', [AccountController::class, 'updateAvatar'])->name('profile.avatar');
    Route::post('/profile/change-password', [AccountController::class, 'changePassword'])->name('profile.change-password');

    // ===== CẤU HÌNH HỆ THỐNG (chỉ Quản lý / Admin) =====
    Route::middleware(['role:manager|admin'])->group(function () {
        Route::get('/settings', [AccountController::class, 'settings'])->name('settings');
    });
});

// Default route redirect to login or dashboard
Route::get('/', function () {
    return redirect()->route('login');
});