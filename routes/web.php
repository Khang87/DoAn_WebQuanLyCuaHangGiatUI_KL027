<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController;
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
Route::middleware(['auth'])->group(function () {

    // Dashboard (Staff & Admin)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Customers Management (Staff & Admin)
    Route::resource('customers', CustomerController::class);

     // Delivery Management (Staff & Admin)
    Route::resource('deliveries', DeliveryController::class)->except(['create', 'store']);

    // Booking Management (Staff & Admin)
    Route::resource('bookings', BookingController::class)->except(['create', 'store']);

    // Payments Management (Staff & Admin)
    Route::resource('payments', PaymentController::class);

    // Invoices Management (Staff & Admin)
    Route::get('invoices/export', [InvoiceController::class, 'export'])->name('invoices.export');
    Route::resource('invoices', InvoiceController::class);

    // ===== ADMIN ONLY ROUTES =====
    Route::middleware(['role:admin'])->group(function () {

        // Orders Management
        Route::resource('orders', OrderController::class);
        Route::resource('order-items', OrderItemController::class);

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
        Route::post('services/{service}/toggle-status', [ServiceController::class, 'toggleStatus'])->name('services.toggle-status');
        Route::post('service-categories/{service_category}/toggle-status', [ServiceCategoryController::class, 'toggleStatus'])->name('service-categories.toggle-status');
    });

    // User Profile & Settings (Staff & Admin)
    Route::get('/profile', [AccountController::class, 'profile'])->name('profile');
    Route::put('/profile', [AccountController::class, 'updateProfile'])->name('profile.update');
    Route::get('/settings', [AccountController::class, 'settings'])->name('settings');
});

// Default route redirect to login or dashboard
Route::get('/', function () {
    return redirect()->route('login');
});
