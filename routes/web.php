<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModifierGroupController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::get('/login', [LoginController::class, 'create'])->name('login')->middleware('guest');
Route::post('/login', [LoginController::class, 'store'])->middleware('guest');
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}/refund', [OrderController::class, 'refund'])->name('orders.refund');

    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store')->middleware('role:admin,manager');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update')->middleware('role:admin,manager');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy')->middleware('role:admin,manager');

    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');

    Route::get('/cash-register', [CashRegisterController::class, 'index'])->name('cash-register.index');
    Route::post('/cash-register/open', [CashRegisterController::class, 'open'])->name('cash-register.open');
    Route::put('/cash-register/{session}/close', [CashRegisterController::class, 'close'])->name('cash-register.close');

    Route::middleware('role:admin,manager')->group(function () {
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');

        Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('/settings/general', [SettingController::class, 'updateGeneral'])->name('settings.general');
        Route::put('/settings/receipt', [SettingController::class, 'updateReceipt'])->name('settings.receipt');
        Route::put('/settings/payment', [SettingController::class, 'updatePayment'])->name('settings.payment');
        Route::put('/settings/tax', [SettingController::class, 'updateTax'])->name('settings.tax');

        Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store');
        Route::put('/purchases/{purchase}/pay', [PurchaseController::class, 'pay'])->name('purchases.pay');
        Route::get('/purchases/{purchase}/payments', [PurchaseController::class, 'payments'])->name('purchases.payments');
        Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

        Route::get('/stock-adjustments', [StockAdjustmentController::class, 'index'])->name('stock-adjustments.index');
        Route::post('/stock-adjustments', [StockAdjustmentController::class, 'store'])->name('stock-adjustments.store');

        Route::get('/modifiers', [ModifierGroupController::class, 'index'])->name('modifiers.index');
        Route::post('/modifiers', [ModifierGroupController::class, 'store'])->name('modifiers.store');
        Route::put('/modifiers/{modifier}', [ModifierGroupController::class, 'update'])->name('modifiers.update');
        Route::delete('/modifiers/{modifier}', [ModifierGroupController::class, 'destroy'])->name('modifiers.destroy');
    });

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/download', [ReportController::class, 'download'])->name('reports.download');

    Route::get('/search', [SearchController::class, 'index'])->name('search');
});
