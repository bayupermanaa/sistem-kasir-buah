<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SalesReportService
{
    public function __construct(private readonly SingleOutletService $singleOutletService)
    {
    }

    public function build(Request $request, ?User $user): array
    {
        [$dateFrom, $dateTo, $preset] = $this->resolveDateRange($request);
        $outletId = $this->singleOutletService->getDefaultOutletId();
        $cashierId = $this->resolveCashierId($request, $user, $outletId);

        $filters = [
            'preset' => $preset,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'cashier_id' => $cashierId,
        ];

        $transactionQuery = $this->baseTransactionQuery($outletId, $filters);
        $itemQuery = $this->baseItemQuery($outletId, $filters);

        $summary = [
            'total_transactions' => (clone $transactionQuery)->count(),
            'total_sales' => (float) (clone $transactionQuery)->sum('total'),
            'total_discount' => (float) (clone $transactionQuery)->sum('discount'),
            'total_weight' => (float) (clone $itemQuery)->sum('transaction_items.quantity'),
            'total_item_lines' => (int) (clone $itemQuery)->count(),
            'average_transaction' => (float) ((clone $transactionQuery)->avg('total') ?? 0),
        ];

        return [
            'summary' => $summary,
            'topProducts' => $this->topProductsQuery($outletId, $filters)->limit(10)->get(),
            'cashierPerformance' => $this->cashierPerformanceQuery($outletId, $filters)->get(),
            'dailyTrends' => $this->dailyTrendQuery($outletId, $filters)->get(),
            'filters' => $filters,
            'cashiers' => $user?->role === 'owner'
                ? User::query()
                    ->where('role', 'cashier')
                    ->where('outlet_id', $outletId)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                : collect(),
            'hasCompletedTransactions' => (clone $transactionQuery)->exists(),
            'reportPeriod' => $this->formatPeriodLabel($filters['date_from'], $filters['date_to']),
            'printedAt' => Carbon::now(),
            'storeName' => $this->singleOutletService->getDefaultOutletName(),
            'selectedCashierName' => $this->selectedCashierName($user, $filters),
        ];
    }

    public function exportFilename(array $filters, string $extension): string
    {
        $from = $filters['date_from'] ?? Carbon::today()->toDateString();
        $to = $filters['date_to'] ?? Carbon::today()->toDateString();

        return sprintf(
            'laporan-penjualan-%s-sampai-%s.%s',
            $from,
            $to,
            ltrim($extension, '.')
        );
    }

    public function exportCsv(array $report): string
    {
        $formatRupiah = fn ($value) => number_format((float) $value, 0, ',', '.');
        $formatWeight = fn ($value) => number_format((float) $value, 3, ',', '.');

        $lines = [];
        $lines[] = ['Ringkasan'];
        $lines[] = ['Periode', $report['reportPeriod']];
        $lines[] = ['Dicetak', $report['printedAt']->translatedFormat('d/m/Y H:i')];
        $lines[] = ['Toko', $report['storeName']];
        $lines[] = ['Kasir', $report['selectedCashierName'] ?? 'Semua kasir'];
        $lines[] = ['Total Transaksi', $report['summary']['total_transactions']];
        $lines[] = ['Total Omzet', $formatRupiah($report['summary']['total_sales'])];
        $lines[] = ['Total Diskon', $formatRupiah($report['summary']['total_discount'])];
        $lines[] = ['Total Berat / Item', $formatWeight($report['summary']['total_weight']).' kg / '.number_format((int) $report['summary']['total_item_lines'], 0, ',', '.').' item'];
        $lines[] = ['Rata-rata Transaksi', $formatRupiah($report['summary']['average_transaction'])];
        $lines[] = [];
        $lines[] = ['Produk Terlaris'];
        $lines[] = ['No', 'Produk', 'Kategori', 'Berat / Item', 'Jumlah Transaksi', 'Omzet'];

        foreach ($report['topProducts'] as $index => $product) {
            $lines[] = [
                $index + 1,
                $product->name,
                $product->category_name,
                $formatWeight($product->total_weight).' kg',
                $product->transaction_count,
                $formatRupiah($product->total_sales),
            ];
        }

        $lines[] = [];
        $lines[] = ['Performa Kasir'];
        $lines[] = ['No', 'Kasir', 'Toko', 'Jumlah Transaksi', 'Omzet', 'Rata-rata Transaksi'];

        foreach ($report['cashierPerformance'] as $index => $row) {
            $lines[] = [
                $index + 1,
                $row->cashier_name,
                $row->store_name,
                $row->transaction_count,
                $formatRupiah($row->total_sales),
                $formatRupiah($row->average_sales),
            ];
        }

        $lines[] = [];
        $lines[] = ['Tren Harian'];
        $lines[] = ['Tanggal', 'Jumlah Transaksi', 'Omzet'];

        foreach ($report['dailyTrends'] as $trend) {
            $lines[] = [
                Carbon::parse($trend->report_date)->translatedFormat('d/m/Y'),
                $trend->transaction_count,
                $formatRupiah($trend->total_sales),
            ];
        }

        $stream = fopen('php://temp', 'r+');
        foreach ($lines as $line) {
            fputcsv($stream, $line);
        }
        rewind($stream);

        return stream_get_contents($stream) ?: '';
    }

    private function resolveDateRange(Request $request): array
    {
        $preset = $request->string('preset')->toString();
        $dateFrom = $this->normalizeDate($request->query('date_from'));
        $dateTo = $this->normalizeDate($request->query('date_to'));

        if ($preset === 'today') {
            return [Carbon::today()->toDateString(), Carbon::today()->toDateString(), $preset];
        }

        if ($preset === '7days') {
            return [Carbon::today()->subDays(6)->toDateString(), Carbon::today()->toDateString(), $preset];
        }

        if ($preset === 'month') {
            return [Carbon::today()->startOfMonth()->toDateString(), Carbon::today()->endOfMonth()->toDateString(), $preset];
        }

        return [$dateFrom, $dateTo, $preset !== '' ? $preset : null];
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->toDateString();
        } catch (\Throwable $throwable) {
            return null;
        }
    }

    private function resolveCashierId(Request $request, ?User $user, int $outletId): ?int
    {
        if ($user?->role === 'cashier') {
            return $user->id;
        }

        $cashierId = $request->integer('cashier_id');

        if (! $cashierId) {
            return null;
        }

        return User::query()
            ->whereKey($cashierId)
            ->where('role', 'cashier')
            ->where('outlet_id', $outletId)
            ->value('id');
    }

    private function baseTransactionQuery(int $outletId, array $filters): Builder
    {
        return Transaction::query()
            ->where('status', 'completed')
            ->where('outlet_id', $outletId)
            ->when($filters['cashier_id'], fn (Builder $query) => $query->where('user_id', $filters['cashier_id']))
            ->when($filters['date_from'], fn (Builder $query) => $query->whereDate('created_at', '>=', Carbon::parse($filters['date_from'])))
            ->when($filters['date_to'], fn (Builder $query) => $query->whereDate('created_at', '<=', Carbon::parse($filters['date_to'])));
    }

    private function baseItemQuery(int $outletId, array $filters): Builder
    {
        return TransactionItem::query()
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.status', 'completed')
            ->where('transactions.outlet_id', $outletId)
            ->when($filters['cashier_id'], fn (Builder $query) => $query->where('transactions.user_id', $filters['cashier_id']))
            ->when($filters['date_from'], fn (Builder $query) => $query->whereDate('transactions.created_at', '>=', Carbon::parse($filters['date_from'])))
            ->when($filters['date_to'], fn (Builder $query) => $query->whereDate('transactions.created_at', '<=', Carbon::parse($filters['date_to'])));
    }

    private function topProductsQuery(int $outletId, array $filters): Builder
    {
        return Product::query()
            ->join('transaction_items', 'products.id', '=', 'transaction_items.product_id')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select([
                'products.id',
                'products.name',
                'categories.name as category_name',
            ])
            ->selectRaw('SUM(transaction_items.quantity) as total_weight')
            ->selectRaw('COUNT(DISTINCT transaction_items.transaction_id) as transaction_count')
            ->selectRaw('SUM(transaction_items.subtotal) as total_sales')
            ->where('transactions.status', 'completed')
            ->where('transactions.outlet_id', $outletId)
            ->when($filters['cashier_id'], fn (Builder $query) => $query->where('transactions.user_id', $filters['cashier_id']))
            ->when($filters['date_from'], fn (Builder $query) => $query->whereDate('transactions.created_at', '>=', Carbon::parse($filters['date_from'])))
            ->when($filters['date_to'], fn (Builder $query) => $query->whereDate('transactions.created_at', '<=', Carbon::parse($filters['date_to'])))
            ->groupBy('products.id', 'products.name', 'categories.name')
            ->orderByDesc('total_sales')
            ->orderByDesc('total_weight');
    }

    private function cashierPerformanceQuery(int $outletId, array $filters): Builder
    {
        return Transaction::query()
            ->join('users', 'transactions.user_id', '=', 'users.id')
            ->join('outlets', 'transactions.outlet_id', '=', 'outlets.id')
            ->select([
                'users.id as cashier_id',
                'users.name as cashier_name',
                'outlets.name as store_name',
            ])
            ->selectRaw('COUNT(*) as transaction_count')
            ->selectRaw('SUM(transactions.total) as total_sales')
            ->selectRaw('AVG(transactions.total) as average_sales')
            ->where('transactions.status', 'completed')
            ->where('transactions.outlet_id', $outletId)
            ->when($filters['cashier_id'], fn (Builder $query) => $query->where('transactions.user_id', $filters['cashier_id']))
            ->when($filters['date_from'], fn (Builder $query) => $query->whereDate('transactions.created_at', '>=', Carbon::parse($filters['date_from'])))
            ->when($filters['date_to'], fn (Builder $query) => $query->whereDate('transactions.created_at', '<=', Carbon::parse($filters['date_to'])))
            ->groupBy('users.id', 'users.name', 'outlets.name')
            ->orderByDesc('total_sales')
            ->orderBy('users.name');
    }

    private function dailyTrendQuery(int $outletId, array $filters): Builder
    {
        return Transaction::query()
            ->selectRaw('DATE(created_at) as report_date')
            ->selectRaw('COUNT(*) as transaction_count')
            ->selectRaw('SUM(total) as total_sales')
            ->where('status', 'completed')
            ->where('outlet_id', $outletId)
            ->when($filters['cashier_id'], fn (Builder $query) => $query->where('user_id', $filters['cashier_id']))
            ->when($filters['date_from'], fn (Builder $query) => $query->whereDate('created_at', '>=', Carbon::parse($filters['date_from'])))
            ->when($filters['date_to'], fn (Builder $query) => $query->whereDate('created_at', '<=', Carbon::parse($filters['date_to'])))
            ->groupByRaw('DATE(created_at)')
            ->orderBy('report_date');
    }

    private function formatPeriodLabel(?string $dateFrom, ?string $dateTo): string
    {
        if ($dateFrom && $dateTo) {
            return Carbon::parse($dateFrom)->translatedFormat('d/m/Y').' - '.Carbon::parse($dateTo)->translatedFormat('d/m/Y');
        }

        if ($dateFrom) {
            return 'Mulai '.Carbon::parse($dateFrom)->translatedFormat('d/m/Y');
        }

        if ($dateTo) {
            return 'Sampai '.Carbon::parse($dateTo)->translatedFormat('d/m/Y');
        }

        return 'Semua periode';
    }

    private function selectedCashierName(?User $user, array $filters): ?string
    {
        if ($user?->role === 'cashier') {
            return $user?->name;
        }

        if (! $filters['cashier_id']) {
            return null;
        }

        return User::query()->whereKey($filters['cashier_id'])->value('name');
    }
}
