<?php

use App\Http\Controllers\Api\V1\DichVuController;
use App\Http\Controllers\Api\V1\DonHangController;
use App\Http\Controllers\Api\V1\GarmentController;
use App\Http\Controllers\Api\V1\KhachHangController;
use App\Http\Controllers\Api\V1\KhuyenMaiController;
use App\Http\Controllers\Api\V1\PasswordRecoveryController;
use App\Http\Controllers\Api\V1\PaymentConfigController;
use App\Http\Controllers\Api\V1\StaffMobileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Hợp đồng JSON mỏng dành cho client mobile. Mọi response dùng chung một cấu
| trúc: {"data": ...} cho show, {"data": [...], "meta": {...}} cho index.
|
| Quyền truy cập dùng đúng bộ role của web: "manager|admin" cho thao tác ghi,
| còn lại chỉ đọc và dành cho cả nhân viên.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    // Public read-only bank details for the customer app.
    Route::get('payment-config', PaymentConfigController::class)->name('payment-config');
    Route::post('auth/forgot-password', [PasswordRecoveryController::class, 'forgot'])
        ->middleware('throttle:5,1')
        ->name('auth.forgot-password');
    Route::post('staff/auth/login', [StaffMobileController::class, 'login'])->middleware('throttle:5,1');
});

Route::middleware('staff.api')->prefix('v1/staff')->group(function () {
    Route::post('auth/logout', [StaffMobileController::class, 'logout']);
    Route::post('auth/change-password', [StaffMobileController::class, 'changePassword']);
    Route::get('profile', [StaffMobileController::class, 'profile']);
    Route::put('profile', [StaffMobileController::class, 'updateProfile']);
    Route::get('orders', [StaffMobileController::class, 'orders']);
    Route::patch('orders/{id}/status', [StaffMobileController::class, 'updateOrderStatus']);
    Route::post('orders/{id}/delivery-progress', [StaffMobileController::class, 'advanceDelivery']);
    Route::get('bookings', [StaffMobileController::class, 'bookings']);
    Route::get('bookings/history', [StaffMobileController::class, 'bookingHistory']);
    Route::get('bookings/{id}', [StaffMobileController::class, 'booking']);
    Route::post('bookings/{id}/estimate', [StaffMobileController::class, 'estimate']);
    Route::post('bookings/{id}/draft', [StaffMobileController::class, 'draft']);
    Route::post('bookings/{id}/confirm', [StaffMobileController::class, 'confirm']);
    Route::post('bookings/{id}/refuse-quote', [StaffMobileController::class, 'refuseQuote']);
});

Route::middleware(['auth', 'reject.customer'])->prefix('v1')->name('api.v1.')->group(function () {
    // Đơn hàng
    Route::get('orders', [DonHangController::class, 'index'])
        ->middleware('permission:orders.view')
        ->name('orders.index');
    Route::get('orders/{order}', [DonHangController::class, 'show'])
        ->middleware('permission:orders.view')
        ->name('orders.show');
    Route::patch('orders/{order}/status', [DonHangController::class, 'updateStatus'])
        ->middleware('permission:orders.update_status')
        ->name('orders.status');

    // Khách hàng
    Route::get('customers', [KhachHangController::class, 'index'])
        ->middleware('permission:customers.view')
        ->name('customers.index');
    Route::get('customers/{customer}', [KhachHangController::class, 'show'])
        ->middleware('permission:customers.view')
        ->name('customers.show');

    // Danh mục động (nhóm lấy từ database, không hardcode)
    Route::get('service-categories', [DichVuController::class, 'categories'])->name('service-categories.index');
    Route::get('garment-categories', [GarmentController::class, 'categories'])->name('garment-categories.index');

    // Dịch vụ
    Route::get('services', [DichVuController::class, 'index'])->name('services.index');

    // Loại đồ giặt
    Route::get('garments', [GarmentController::class, 'index'])->name('garments.index');

    // Khuyến mãi (chỉ voucher còn hiệu lực)
    Route::get('promotions', [KhuyenMaiController::class, 'index'])->name('promotions.index');
});
