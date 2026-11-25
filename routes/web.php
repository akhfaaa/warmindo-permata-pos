<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('pos.index'));

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth', 'role:OWNER,KASIR'])->group(function () {
    // Layar Utama Kasir (POS)
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');

    // Riwayat Transaksi Harian
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');

    // Shift & Tutup Kasir
    Route::get('/shift-close', [ShiftController::class, 'index'])->name('shifts.index');
    Route::post('/shift-close/open', [ShiftController::class, 'open'])->name('shifts.open');
    Route::post('/shift-close/{shift}/close', [ShiftController::class, 'close'])->name('shifts.close');

    // Inventori & Restock
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('/inventory/restock', [InventoryController::class, 'restock'])->name('inventory.restock');
});

Route::middleware(['auth', 'role:OWNER'])->group(function () {
    // Void Transaksi (Owner Only)
    Route::post('/transactions/{transaction}/void', [TransactionController::class, 'void'])->name('transactions.void');

    // Tambah Bahan Baku Baru & Stock Opname (Owner Only)
    Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
    Route::post('/inventory/opname', [InventoryController::class, 'opname'])->name('inventory.opname');

    // Katalog Menu, Topping & Resep BOM (Owner Only)
    Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
    Route::post('/catalog/products', [CatalogController::class, 'storeProduct'])->name('catalog.products.store');
    Route::put('/catalog/products/{product}', [CatalogController::class, 'updateProduct'])->name('catalog.products.update');
    Route::post('/catalog/modifiers', [CatalogController::class, 'storeModifier'])->name('catalog.modifiers.store');
    Route::post('/catalog/recipes/sync', [CatalogController::class, 'syncRecipe'])->name('catalog.recipes.sync');
});