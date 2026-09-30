<?php

use App\Http\Controllers\Api\V1\DichVuController;
use App\Http\Controllers\Api\V1\DonHangController;
use App\Http\Controllers\Api\V1\GarmentController;
use App\Http\Controllers\Api\V1\KhachHangController;
use App\Http\Controllers\Api\V1\KhuyenMaiController;
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
