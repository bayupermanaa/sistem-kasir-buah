<?php

namespace App\Http\Controllers;

use App\Models\AccountingSetting;
use App\Models\JournalEntry;
use App\Models\OutletProductStock;
use App\Models\SupplierPayable;
use App\Models\Transaction;
use App\Services\ProfitLossService;
use App\Services\SingleOutletService;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __construct(
        private readonly SingleOutletService $singleOutletService,
        private readonly ProfitLossService $profitLossService,
    ) {
    }

    public function index()
    {
        $outlet = $this->singleOutletService->getDefaultOutlet();
        $today = Carbon::today();
        $outletId = (int) $outlet->id;

        $todayTransactions = Transaction::query()
            ->where('outlet_id', $outletId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()]);

        $stockWarnings = OutletProductStock::query()
            ->with('product:id,name,code')
            ->where('outlet_id', $outletId)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->where('minimum_stock', '>', 0)
                        ->whereColumn('quantity', '<=', 'minimum_stock');
                })->orWhere('quantity', '<=', 0);
            })
            ->orderBy('quantity')
            ->limit(5)
            ->get();

        $trendStart = $today->copy()->subDays(6);
        $trendRows = Transaction::query()
            ->selectRaw('DATE(created_at) as sale_date, COUNT(*) as transaction_count, SUM(total) as total_sales')
            ->where('outlet_id', $outletId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$trendStart->copy()->startOfDay(), $today->copy()->endOfDay()])
            ->groupBy('sale_date')
            ->get()
            ->keyBy('sale_date');

        $salesTrend = collect(range(0, 6))->map(function (int $offset) use ($trendStart, $trendRows): array {
            $date = $trendStart->copy()->addDays($offset);
            $row = $trendRows->get($date->toDateString());

            return [
                'date' => $date,
                'transaction_count' => (int) ($row?->transaction_count ?? 0),
                'total_sales' => (float) ($row?->total_sales ?? 0),
            ];
        });

        $accounting = $this->accountingStatus($outletId, $today);

        return view('dashboard', [
            'outlet' => $outlet,
            'todaySales' => (float) ((clone $todayTransactions)->sum('total') ?? 0),
            'todayTransactionCount' => (clone $todayTransactions)->count(),
            'lowStockCount' => OutletProductStock::query()
                ->where('outlet_id', $outletId)
                ->whereHas('product', fn ($query) => $query->where('is_active', true))
                ->where(function ($query): void {
                    $query->where(function ($query): void {
                        $query->where('minimum_stock', '>', 0)
                            ->whereColumn('quantity', '<=', 'minimum_stock');
                    })->orWhere('quantity', '<=', 0);
                })
                ->count(),
            'outstandingPayables' => (float) SupplierPayable::query()
                ->where('outlet_id', $outletId)
                ->whereIn('status', ['open', 'partial'])
                ->sum('outstanding_amount'),
            'salesTrend' => $salesTrend,
            'recentTransactions' => Transaction::query()
                ->with('user:id,name')
                ->where('outlet_id', $outletId)
                ->where('status', 'completed')
                ->latest('created_at')
                ->limit(5)
                ->get(['id', 'invoice_number', 'user_id', 'total', 'payment_method', 'created_at']),
            'stockWarnings' => $stockWarnings,
            'accounting' => $accounting,
        ]);
    }

    private function accountingStatus(int $outletId, Carbon $today): array
    {
        $cutoverDate = AccountingSetting::query()
            ->where('outlet_id', $outletId)
            ->value('cutover_date');
        $hasOpeningBalance = JournalEntry::query()
            ->where('outlet_id', $outletId)
            ->where('journal_type', 'opening_balance')
            ->exists();

        if (! $cutoverDate || ! $hasOpeningBalance) {
            return [
                'active' => false,
                'hpp_complete' => null,
                'messages' => [
                    'Saldo awal belum diposting atau tanggal cutover belum ditetapkan.',
                    'Laporan keuangan belum dianggap final.',
                ],
            ];
        }

        $profitLoss = $this->profitLossService->build(
            $outletId,
            Carbon::parse($cutoverDate)->toDateString(),
            $today->toDateString(),
        );

        return [
            'active' => true,
            'hpp_complete' => (bool) ($profitLoss['hpp_complete'] ?? false),
            'messages' => ($profitLoss['hpp_complete'] ?? false)
                ? []
                : ['HPP belum lengkap untuk seluruh transaksi setelah cutover.'],
        ];
    }
}
