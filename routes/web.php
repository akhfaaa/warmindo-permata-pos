<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\PrinterSettingController;
use App\Http\Controllers\RecapController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\ReportsController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! Auth::check()) {
        return redirect()->route('login');
    }
    return redirect()->route(Auth::user()->defaultRouteName());
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth'])->group(function () {
    // Dashboard Laporan Omzet & Laba Kotor
    Route::middleware(['menu:dashboard'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
        Route::get('/dashboard/export', [DashboardController::class, 'exportCsv'])->name('dashboard.export');
    });

    // Layar Utama Kasir (POS)
    Route::middleware(['menu:pos'])->group(function () {
        Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
        Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
    });

    // Riwayat Transaksi Harian & Void
    Route::middleware(['menu:transactions'])->group(function () {
        Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::post('/transactions/{transaction}/void', [TransactionController::class, 'void'])
            ->middleware('role:ADMINISTRATOR,ADMIN_UTAMA')
            ->name('transactions.void');
    });

    // Rekap Harian Penjualan, Rekap Belanja & Pendapatan Bulanan
    Route::middleware(['menu:recap'])->group(function () {
        Route::get('/recap', [RecapController::class, 'index'])->name('recap.index');
        Route::put('/recap/purchases/{movement}', [RecapController::class, 'updatePurchase'])
            ->middleware('role:ADMINISTRATOR,ADMIN_UTAMA')
            ->name('recap.purchases.update');
        Route::delete('/recap/purchases/{movement}', [RecapController::class, 'destroyPurchase'])
            ->middleware('role:ADMINISTRATOR,ADMIN_UTAMA')
            ->name('recap.purchases.destroy');
    });

    // Shift & Tutup Kasir
    Route::middleware(['menu:shifts'])->group(function () {
        Route::get('/shift-close', [ShiftController::class, 'index'])->name('shifts.index');
        Route::post('/shift-close/open', [ShiftController::class, 'open'])->name('shifts.open');
        Route::post('/shift-close/{shift}/close', [ShiftController::class, 'close'])->name('shifts.close');
    });

    // Inventori & Restock
    Route::middleware(['menu:inventory'])->group(function () {
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::post('/inventory/restock', [InventoryController::class, 'restock'])->name('inventory.restock');

        Route::middleware('role:ADMINISTRATOR,ADMIN_UTAMA')->group(function () {
            Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
            Route::post('/inventory/opname', [InventoryController::class, 'opname'])->name('inventory.opname');
            Route::put('/inventory/{ingredient}', [InventoryController::class, 'update'])->name('inventory.update');
            Route::delete('/inventory/{ingredient}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
        });
    });

    // Katalog Menu, Topping & Resep BOM
    Route::middleware(['menu:catalog'])->group(function () {
        Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
        Route::post('/catalog/products', [CatalogController::class, 'storeProduct'])->name('catalog.products.store');
        Route::post('/catalog/modifiers', [CatalogController::class, 'storeModifier'])->name('catalog.modifiers.store');
        Route::post('/catalog/recipes/sync', [CatalogController::class, 'syncRecipe'])->name('catalog.recipes.sync');

        Route::middleware('role:ADMINISTRATOR,ADMIN_UTAMA')->group(function () {
            Route::put('/catalog/products/{product}', [CatalogController::class, 'updateProduct'])->name('catalog.products.update');
            Route::delete('/catalog/products/{product}', [CatalogController::class, 'destroyProduct'])->name('catalog.products.destroy');
            Route::put('/catalog/modifiers/{modifier}', [CatalogController::class, 'updateModifier'])->name('catalog.modifiers.update');
            Route::delete('/catalog/modifiers/{modifier}', [CatalogController::class, 'destroyModifier'])->name('catalog.modifiers.destroy');
        });
    });

    // Pengaturan Printer Thermal & Monitor Sync Offline
    Route::middleware(['menu:printer'])->group(function () {
        Route::get('/settings/printer', [PrinterSettingController::class, 'index'])->name('settings.printer');
    });

    // Manajemen User & Hak Akses Menu (Administrator)
    Route::middleware(['menu:users'])->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
    });

   // Pusat Laporan (6 Laporan Operasional & Keuangan + Export CSV & Custom PDF)
    Route::middleware(['menu:reports'])->group(function () {
        Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportsController::class, 'exportCsv'])->name('reports.export');
        Route::get('/reports/pdf', [ReportsController::class, 'exportPdf'])->name('reports.pdf');
    });
});