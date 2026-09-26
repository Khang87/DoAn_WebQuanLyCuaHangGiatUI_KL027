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
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\GarmentConditionController;
use App\Http\Controllers\Admin\GarmentController;
use App\Http\Controllers\Admin\ReviewController;

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
    });

    // ===== STAFF DASHBOARD (Employee) =====
    Route::middleware(['role:staff|employee'])->group(function () {
        Route::get('/staff/dashboard', [StaffDashboardController::class, 'index'])->name('staff.dashboard');
        Route::patch('/staff/dashboard/orders/{order}/status', [StaffDashboardController::class, 'updateOrderStatus'])->name('staff.dashboard.update-order-status');
    });

    // Customers Management (Staff & Admin)
    Route::resource('customers', CustomerController::class);

    // Orders Management (Staff & Admin)
    Route::resource('orders', OrderController::class);
    Route::resource('order-items', OrderItemController::class);

    // Delivery Management (Staff & Admin)
    Route::resource('deliveries', DeliveryController::class);

    // Booking Management (Staff & Admin)
    Route::resource('bookings', BookingController::class)->except(['create', 'store']);
    Route::post('bookings/{booking}/confirm', [BookingController::class, 'confirm'])->name('bookings.confirm');

    // Payments Management (chỉ Quản lý / Admin — tiền nặng)
    Route::middleware(['role:manager|admin'])->group(function () {
        Route::resource('payments', PaymentController::class);

        // Invoices Management (chỉ Quản lý / Admin — quyết toán tài chính)
        Route::get('invoices/export', [InvoiceController::class, 'export'])->name('invoices.export');
        Route::get('invoices/{invoice}/export-excel', [InvoiceController::class, 'exportExcel'])->name('invoices.export-excel');
        Route::post('invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->name('invoices.update-status');
        Route::resource('invoices', InvoiceController::class);
    });

    // Reviews Management (Staff & Admin)
    Route::resource('reviews', ReviewController::class)->only(['index', 'show']);
    Route::patch('reviews/{review}/respond', [ReviewController::class, 'respond'])->name('reviews.respond');

    // ===== ADMIN ONLY ROUTES =====
    Route::middleware(['role:manager|admin'])->group(function () {
        Route::patch('reviews/{review}/toggle', [ReviewController::class, 'toggleStatus'])->name('reviews.toggle');

        // Services Management
        Route::resource('service-categories', ServiceCategoryController::class);
        Route::resource('services', ServiceController::class);

        // Garments Management
        Route::resource('garments', GarmentController::class);
        Route::resource('garment-conditions', GarmentConditionController::class);

        // Pricing Management
        Route::resource('pricings', PricingController::class);

        // Promotions & Coupons Management
        Route::resource('promotions', PromotionController::class);
        Route::resource('coupons', CouponController::class);

        // Reports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [ReportController::class, 'index'])->name('index');
            Route::get('/revenue', [ReportController::class, 'revenue'])->name('revenue');
            Route::get('/orders', [ReportController::class, 'orders'])->name('orders');
            Route::get('/customers', [ReportController::class, 'customers'])->name('customers');
        });

        // Accounts Management
        Route::resource('accounts', AccountController::class);
        Route::post('accounts/{account}/toggle-status', [AccountController::class, 'toggleStatus'])->name('accounts.toggle-status');
        Route::post('accounts/{account}/reset-password', [AccountController::class, 'resetPassword'])->name('accounts.reset-password');

        // Notifications Management
        Route::resource('notifications', NotificationController::class);

        // Services Management - add toggle status
        // Route::post('services/{service}/toggle-status', [ServiceController::class, 'toggleStatus'])->name('services.toggle-status');
        Route::post('service-categories/{service_category}/toggle-status', [ServiceController::class, 'toggleStatus'])->name('service-categories.toggle-status');
    });

    // User Profile (Staff & Admin) — ai cũng tự sửa được hồ sơ của mình
    Route::get('/profile', [AccountController::class, 'profile'])->name('profile');
    Route::put('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');
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