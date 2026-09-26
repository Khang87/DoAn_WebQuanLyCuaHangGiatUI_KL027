<?php

use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\GarmentController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PromotionController;
use App\Http\Controllers\Api\V1\ServiceController;
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

Route::middleware(['auth', 'reject.customer'])->prefix('v1')->name('api.v1.')->group(function () {
    // Đơn hàng
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])
        ->middleware('role:manager|admin')
        ->name('orders.status');

    // Khách hàng
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');

    // Danh mục động (nhóm lấy từ database, không hardcode)
    Route::get('service-categories', [ServiceController::class, 'categories'])->name('service-categories.index');
    Route::get('garment-categories', [GarmentController::class, 'categories'])->name('garment-categories.index');

    // Dịch vụ
    Route::get('services', [ServiceController::class, 'index'])->name('services.index');

    // Loại đồ giặt
    Route::get('garments', [GarmentController::class, 'index'])->name('garments.index');

    // Khuyến mãi (chỉ voucher còn hiệu lực)
    Route::get('promotions', [PromotionController::class, 'index'])->name('promotions.index');
});
