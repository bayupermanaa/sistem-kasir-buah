<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CashierTransactionController;
use App\Http\Controllers\TransactionHistoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\SalesReportController;
use App\Http\Controllers\StockOpnameController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockHistoryController;
use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::view('/dashboard', 'dashboard')
    ->middleware(['auth', 'role:owner'])
    ->name('dashboard');

Route::middleware(['auth', 'role:owner,cashier'])->group(function () {
    Route::get('/kasir', [CashierTransactionController::class, 'index'])->name('kasir');
    Route::post('/kasir', [CashierTransactionController::class, 'store'])->name('kasir.store');
    Route::get('/kasir/transaksi/{transaction}', [CashierTransactionController::class, 'show'])->name('kasir.show');
    Route::get('/kasir/transaksi/{transaction}/print', [CashierTransactionController::class, 'print'])->name('kasir.print');
    Route::get('/riwayat-transaksi', [TransactionHistoryController::class, 'index'])->name('transactions.index');
    Route::get('/pengeluaran', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('/pengeluaran/create', [ExpenseController::class, 'create'])->name('expenses.create');
    Route::post('/pengeluaran', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::get('/laporan-penjualan', [SalesReportController::class, 'index'])->name('reports.sales');
    Route::get('/laporan-penjualan/export/pdf', [SalesReportController::class, 'exportPdf'])->name('reports.sales.export.pdf');
    Route::get('/laporan-penjualan/export/excel', [SalesReportController::class, 'exportExcel'])->name('reports.sales.export.excel');
});

Route::middleware(['auth', 'role:owner'])->group(function () {
    Route::get('/pengaturan', [SettingsController::class, 'index'])->name('pengaturan');
    Route::put('/pengaturan/kasir/{cashier}/password', [SettingsController::class, 'updateCashierPassword'])
        ->whereNumber('cashier')
        ->name('pengaturan.cashiers.password.update');
});

Route::middleware(['auth', 'role:owner,cashier'])->group(function () {
    Route::get('/barang-stok', [StockController::class, 'index'])->name('products.index');
    Route::get('/barang-stok/{product}/adjust', [StockController::class, 'createAdjustmentForm'])->name('stocks.adjust.create');
    Route::post('/barang-stok/{product}/adjust', [StockController::class, 'storeAdjustment'])->name('stocks.adjust.store');
    Route::get('/riwayat-stok', [StockHistoryController::class, 'index'])->name('stock.histories.index');
});

Route::middleware(['auth', 'role:owner'])->group(function () {
    Route::get('/pengeluaran/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
    Route::put('/pengeluaran/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
    Route::delete('/pengeluaran/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

    Route::get('/stock-opname', [StockOpnameController::class, 'index'])->name('stock.opnames.index');
    Route::get('/stock-opname/create', [StockOpnameController::class, 'create'])->name('stock.opnames.create');
    Route::post('/stock-opname', [StockOpnameController::class, 'store'])->name('stock.opnames.store');
    Route::get('/stock-opname/{stockOpname}', [StockOpnameController::class, 'show'])->name('stock.opnames.show');

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
