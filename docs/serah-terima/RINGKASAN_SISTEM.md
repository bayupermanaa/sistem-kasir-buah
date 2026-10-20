# Ringkasan Sistem Kasir Buah

## Tujuan
Sistem kasir web untuk operasional Toko Buah Teras Fatimah dalam mode single outlet.

## Teknologi
- Laravel 10, PHP, Blade, Bootstrap 5, Bootstrap Icons.
- MySQL untuk development/operasional dan Vite untuk asset frontend.
- Accounting ledger double-entry dengan jurnal debit/kredit.

## Role
- **Owner:** dashboard, master data, stok, pembelian, supplier/utang, aset, pengeluaran, saldo awal, dan laporan keuangan.
- **Cashier:** kasir, stok outlet utama, penyesuaian sesuai izin, riwayat transaksi/stok, dan laporan penjualan.

## Modul dan Lokasi Utama
| Modul | Route utama | Controller/service | View utama | Test |
|---|---|---|---|---|
| Login/logout | `/login`, `/logout` | `AuthenticatedSessionController` | `auth/login.blade.php` | `AuthenticationTest` |
| Dashboard | `/dashboard` | `DashboardController` | `dashboard.blade.php` | `DashboardTest` |
| Kasir | `/kasir` | `CashierTransactionController`, `TransactionService` | `kasir/index.blade.php` | `CashierTransactionTest`, `TransactionServiceTest` |
| Produk/kategori/satuan | `/barang-stok`, `/categories`, `/units` | `ProductController`, `CategoryController`, `UnitController` | `products`, `categories`, `units` | `CatalogCrudTest` |
| Stok | `/barang-stok`, `/riwayat-stok` | `StockController`, `StockService`, `StockHistoryController` | `products/index`, `stocks`, `stock-histories` | `StockPrecisionTest`, `StockPurchaseAccountingTest` |
| Stock opname | `/stock-opname` | `StockOpnameController`, `StockOpnameService` | `stock-opnames` | `StockOpnameTest` |
| Transaksi | `/riwayat-transaksi` | `TransactionHistoryController` | `transactions/index` | `TransactionHistoryTest` |
| Pengeluaran | `/pengeluaran` | `ExpenseController`, `ExpenseAccountingService` | `expenses` | `ExpenseCrudTest`, `ExpenseAccountingTest` |
| Supplier/utang | `/suppliers`, `/utang-usaha` | `SupplierController`, `SupplierPayableController`, `SupplierPayableService` | `suppliers`, `payables` | `SupplierPayableTest` |
| Aset | `/aset` | `AssetController`, `AssetAccountingService` | `assets` | `AssetCrudTest`, `AssetAccountingTest` |
| Saldo awal | `/pengaturan/saldo-awal` | `OpeningBalanceController`, `OpeningBalanceService` | `accounting/opening-balance` | `OpeningBalanceTest` |
| Laporan | `/laporan-penjualan`, `/laporan-laba-rugi`, `/neraca`, `/laporan-arus-kas` | `SalesReportController` dan service laporan | `reports` | `SalesReportTest`, `ProfitLossLedgerTest`, `BalanceSheetLedgerTest`, `CashFlowLedgerTest` |

## Batasan
Saldo awal belum diposting dan cutover accounting belum diaktifkan. Validasi browser, printer, concurrency MySQL, dan rekonsiliasi data nyata klien masih wajib dilakukan.
