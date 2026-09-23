<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\PricingController;
use App\Http\Controllers\Admin\DeliveryController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\NotificationController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Authentication Routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

use App\Http\Controllers\Admin\GarmentController;

// Protected Routes
Route::middleware(['auth'])->group(function () {
    
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Orders Management
    Route::resource('orders', OrderController::class);
    
    // Customers Management
    Route::resource('customers', CustomerController::class);
    
    // Services Management
    Route::resource('services', ServiceController::class);

    // Garments Management
    Route::resource('garments', GarmentController::class);
    
    // Pricing Management
    Route::resource('pricings', PricingController::class)->middleware('role:admin');
    
    // Delivery Management
    Route::resource('deliveries', DeliveryController::class);
    
    // Payments Management
    Route::resource('payments', PaymentController::class);
    
    // Invoices Management
    Route::resource('invoices', InvoiceController::class);
    
    // Promotions Management
    Route::resource('promotions', PromotionController::class)->middleware('role:admin');
    
    // Reports
    Route::prefix('reports')->name('reports.')->middleware('role:admin')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/revenue', [ReportController::class, 'revenue'])->name('revenue');
        Route::get('/orders', [ReportController::class, 'orders'])->name('orders');
        Route::get('/customers', [ReportController::class, 'customers'])->name('customers');
    });
    
    // Accounts Management
    Route::resource('accounts', AccountController::class)->middleware('role:admin');
    
    // Notifications Management
    Route::resource('notifications', NotificationController::class)->middleware('role:admin');
});

// Default route redirect to login or dashboard
Route::get('/', function () {
    return redirect()->route('login');
});
