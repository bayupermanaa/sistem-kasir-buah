<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockHistoryController;
use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::view('/dashboard', 'dashboard')
    ->middleware(['auth', 'role:owner'])
    ->name('dashboard');

Route::view('/kasir', 'kasir')
    ->middleware(['auth', 'role:owner,cashier'])
    ->name('kasir');

Route::view('/pengaturan', 'pengaturan')
    ->middleware(['auth', 'role:owner'])
    ->name('pengaturan');

Route::middleware(['auth', 'role:owner,cashier'])->group(function () {
    Route::get('/barang-stok', [StockController::class, 'index'])->name('products.index');
    Route::get('/barang-stok/{product}/adjust', [StockController::class, 'createAdjustmentForm'])->name('stocks.adjust.create');
    Route::post('/barang-stok/{product}/adjust', [StockController::class, 'storeAdjustment'])->name('stocks.adjust.store');
    Route::get('/riwayat-stok', [StockHistoryController::class, 'index'])->name('stock.histories.index');
});

Route::middleware(['auth', 'role:owner'])->group(function () {
    Route::get('/barang-stok/{outletProductStock}/minimum-stock', [StockController::class, 'editMinimumStock'])->name('stocks.minimum.edit');
    Route::put('/barang-stok/{outletProductStock}/minimum-stock', [StockController::class, 'updateMinimumStock'])->name('stocks.minimum.update');
});

Route::middleware(['auth', 'role:owner'])->group(function () {
    Route::get('/barang-stok/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('/barang-stok', [ProductController::class, 'store'])->name('products.store');
    Route::get('/barang-stok/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/barang-stok/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/barang-stok/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('/units', [UnitController::class, 'index'])->name('units.index');
    Route::get('/units/create', [UnitController::class, 'create'])->name('units.create');
    Route::post('/units', [UnitController::class, 'store'])->name('units.store');
    Route::get('/units/{unit}/edit', [UnitController::class, 'edit'])->name('units.edit');
    Route::put('/units/{unit}', [UnitController::class, 'update'])->name('units.update');
    Route::delete('/units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
