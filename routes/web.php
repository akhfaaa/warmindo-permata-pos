<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\InventoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('pos.index'));

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth', 'role:OWNER,KASIR'])->group(function () {
    // Placeholder sementara sebelum Fase 3
    Route::get('/pos', function () {
        return view('layouts.app', ['slot' => '']);
    })->name('pos.index');

    // Inventori & Restock (Kasir & Owner)
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('/inventory/restock', [InventoryController::class, 'restock'])->name('inventory.restock');
});

Route::middleware(['auth', 'role:OWNER'])->group(function () {
    // Tambah bahan baku baru & Stock Opname (Owner Only)
    Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');
    Route::post('/inventory/opname', [InventoryController::class, 'opname'])->name('inventory.opname');

    // Katalog Menu, Topping & Resep BOM (Owner Only)
    Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
    Route::post('/catalog/products', [CatalogController::class, 'storeProduct'])->name('catalog.products.store');
    Route::put('/catalog/products/{product}', [CatalogController::class, 'updateProduct'])->name('catalog.products.update');
    Route::post('/catalog/modifiers', [CatalogController::class, 'storeModifier'])->name('catalog.modifiers.store');
    Route::post('/catalog/recipes/sync', [CatalogController::class, 'syncRecipe'])->name('catalog.recipes.sync');
});