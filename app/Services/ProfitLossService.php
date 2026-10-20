<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\AccountingSetting;
use App\Models\JournalEntry;
use App\Models\TransactionItem;
use Illuminate\Support\Carbon;

class ProfitLossService
{
    private const ACCOUNT_SUBTYPES = [
        'sales' => 'Penjualan',
        'cost_of_goods_sold' => 'HPP',
        'operating_expense' => 'Beban Operasional',
    ];

    public function __construct(private readonly AccountingLedgerService $ledger)
    {
    }

    public function build(int $outletId, ?string $dateFrom, ?string $dateTo, array $historicalFallback = []): array
    {
        $requestedFrom = $dateFrom ? Carbon::parse($dateFrom)->toDateString() : null;
        $requestedTo = $dateTo ? Carbon::parse($dateTo)->toDateString() : Carbon::today()->toDateString();
        $setting = AccountingSetting::query()->where('outlet_id', $outletId)->first();
        $cutoverDate = $setting?->cutover_date?->toDateString();
        $hasOpeningBalance = JournalEntry::query()
            ->where('outlet_id', $outletId)
            ->where('journal_type', 'opening_balance')
            ->exists();

        if (! $cutoverDate || ! $hasOpeningBalance || $requestedTo < $cutoverDate) {
            return array_merge($historicalFallback, [
                'source' => 'historical_operational',
                'accounting_active' => false,
                'accounting_complete' => false,
                'period_from' => $requestedFrom,
                'period_to' => $requestedTo,
                'cutover_date' => $cutoverDate,
                'coverage_note' => 'Accounting belum aktif. Nilai laporan mengikuti data operasional lama dan belum dapat dianggap sebagai laporan ledger final.',
            ]);
        }

        $periodFrom = $requestedFrom && $requestedFrom > $cutoverDate ? $requestedFrom : $cutoverDate;
        $accounts = AccountingAccount::query()
            ->whereIn('subtype', array_keys(self::ACCOUNT_SUBTYPES))
            ->where('is_active', true)
            ->get()
            ->keyBy('subtype');
        $missingAccounts = collect(array_diff(array_keys(self::ACCOUNT_SUBTYPES), $accounts->keys()->all()))
            ->map(fn (string $subtype): string => self::ACCOUNT_SUBTYPES[$subtype])
            ->values();

        if ($missingAccounts->isNotEmpty()) {
            return $this->unavailable($periodFrom, $requestedTo, $cutoverDate, 'Akun Laba Rugi belum lengkap: '.$missingAccounts->implode(', ').'.');
        }

        $rawBalances = [];
        foreach (array_keys(self::ACCOUNT_SUBTYPES) as $subtype) {
            $rawBalances[$subtype] = $this->parseAmount(
                $this->ledger->accountBalance($accounts[$subtype]->id, $outletId, $periodFrom, $requestedTo)['balance']
            );
        }

        $revenue = -$rawBalances['sales'];
        $hpp = $rawBalances['cost_of_goods_sold'];
        $operatingExpenses = $rawBalances['operating_expense'];
        $missingHppProducts = $this->missingHppProducts($outletId, $periodFrom, $requestedTo);
        $hppComplete = $missingHppProducts->isEmpty();

        return [
            'source' => 'accounting_ledger',
            'accounting_active' => true,
            'accounting_complete' => $hppComplete,
            'period_from' => $periodFrom,
            'period_to' => $requestedTo,
            'cutover_date' => $cutoverDate,
            'revenue_before_discount' => null,
            'discount_total' => null,
            'net_sales' => $this->formatCents($revenue),
            'hpp' => $hppComplete ? $this->formatCents($hpp) : null,
            'gross_profit' => $hppComplete ? $this->formatCents($revenue - $hpp) : null,
            'operating_expenses' => $this->formatCents($operatingExpenses),
            'net_profit' => $hppComplete ? $this->formatCents($revenue - $hpp - $operatingExpenses) : null,
            'hpp_complete' => $hppComplete,
            'missing_hpp_products' => $missingHppProducts,
            'coverage_note' => $requestedFrom && $requestedFrom < $cutoverDate
                ? 'Periode sebelum cutover tidak dihitung dalam ledger; laporan dimulai dari tanggal cutover.'
                : 'Laporan menggunakan jurnal ledger pada periode yang dipilih.',
        ];
    }

    private function unavailable(string $periodFrom, string $periodTo, ?string $cutoverDate, string $note): array
    {
        return [
            'source' => 'accounting_ledger',
            'accounting_active' => true,
            'accounting_complete' => false,
            'period_from' => $periodFrom,
            'period_to' => $periodTo,
            'cutover_date' => $cutoverDate,
            'revenue_before_discount' => null,
            'discount_total' => null,
            'net_sales' => null,
            'hpp' => null,
            'gross_profit' => null,
            'operating_expenses' => null,
            'net_profit' => null,
            'hpp_complete' => false,
            'missing_hpp_products' => collect(),
            'coverage_note' => $note,
        ];
    }

    private function missingHppProducts(int $outletId, string $dateFrom, string $dateTo): \Illuminate\Support\Collection
    {
        return TransactionItem::query()
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->leftJoin('products', 'transaction_items.product_id', '=', 'products.id')
            ->where('transactions.outlet_id', $outletId)
            ->where('transactions.status', 'completed')
            ->whereDate('transactions.created_at', '>=', $dateFrom)
            ->whereDate('transactions.created_at', '<=', $dateTo)
            ->whereNull('products.purchase_price')
            ->select(['products.name', 'transaction_items.product_id'])
            ->distinct()
            ->get()
            ->map(fn ($row): string => $row->name ?: 'Produk #'.$row->product_id)
            ->values();
    }

    private function parseAmount(mixed $value): int
    {
        $text = trim((string) $value);
        $negative = str_starts_with($text, '-');
        $text = ltrim($text, '-');
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/', $text)) {
            return 0;
        }

        [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '0');
        $amount = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

        return $negative ? -$amount : $amount;
    }

    private function formatCents(int $cents): string
    {
        if ($cents < 0) {
            return '-'.$this->formatCents(abs($cents));
        }

        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
