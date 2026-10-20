<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\AccountingSetting;
use App\Models\JournalEntry;
use App\Models\TransactionItem;
use Illuminate\Support\Carbon;

class BalanceSheetService
{
    private const ACCOUNT_SUBTYPES = [
        'cash' => 'Kas Tunai',
        'bank' => 'Bank/QRIS',
        'inventory' => 'Persediaan',
        'fixed_asset' => 'Aset Tetap',
        'payable' => 'Utang Supplier',
        'capital' => 'Modal Pemilik',
        'sales' => 'Penjualan',
        'cost_of_goods_sold' => 'HPP',
        'operating_expense' => 'Beban Operasional',
    ];

    public function __construct(private readonly AccountingLedgerService $ledger)
    {
    }

    public function build(int $outletId, ?string $asOfDate): array
    {
        $date = Carbon::parse($asOfDate ?: Carbon::today()->toDateString())->toDateString();
        $setting = AccountingSetting::query()->where('outlet_id', $outletId)->first();
        $cutoverDate = $setting?->cutover_date?->toDateString();
        $hasOpeningBalance = JournalEntry::query()
            ->where('outlet_id', $outletId)
            ->where('journal_type', 'opening_balance')
            ->exists();

        if (! $cutoverDate || ! $hasOpeningBalance) {
            return $this->unavailable($date, 'Pembukuan belum aktif karena saldo awal dan tanggal cutover belum ditetapkan.');
        }

        if ($date < $cutoverDate) {
            return $this->unavailable($date, 'Tanggal posisi berada sebelum tanggal mulai pembukuan.');
        }

        $accounts = AccountingAccount::query()
            ->whereIn('subtype', array_keys(self::ACCOUNT_SUBTYPES))
            ->where('is_active', true)
            ->get()
            ->keyBy('subtype');
        $missingAccounts = collect(array_diff(array_keys(self::ACCOUNT_SUBTYPES), $accounts->keys()->all()))
            ->map(fn (string $subtype): string => self::ACCOUNT_SUBTYPES[$subtype])
            ->values();

        if ($missingAccounts->isNotEmpty()) {
            return $this->unavailable($date, 'Akun ledger belum lengkap: '.$missingAccounts->implode(', ').'.', $missingAccounts->all());
        }

        $balances = [];
        foreach (array_keys(self::ACCOUNT_SUBTYPES) as $subtype) {
            $rawBalance = $this->parseAmount(
                $this->ledger->accountBalance($accounts[$subtype]->id, $outletId, $cutoverDate, $date)['balance']
            );
            $balances[$subtype] = in_array($subtype, ['payable', 'capital', 'sales'], true)
                ? -$rawBalance
                : $rawBalance;
        }

        $missingHppProducts = $this->missingHppProducts($outletId, $cutoverDate, $date);
        $hppComplete = $missingHppProducts->isEmpty();
        $profit = $hppComplete
            ? $balances['sales'] - $balances['cost_of_goods_sold'] - $balances['operating_expense']
            : null;

        $totalAssets = $balances['cash'] + $balances['bank'] + $balances['inventory'] + $balances['fixed_asset'];
        $totalEquity = $profit === null ? null : $balances['capital'] + $profit;
        $totalLiabilitiesEquity = $totalEquity === null ? null : $balances['payable'] + $totalEquity;
        $difference = $totalLiabilitiesEquity === null ? null : $totalAssets - $totalLiabilitiesEquity;

        return [
            'available' => true,
            'accounting_active' => true,
            'accounting_complete' => $difference === 0 && $hppComplete,
            'as_of_date' => $date,
            'cutover_date' => $cutoverDate,
            'assets' => [
                'cash' => $this->formatCents($balances['cash']),
                'bank' => $this->formatCents($balances['bank']),
                'cash_total' => $this->formatCents($balances['cash'] + $balances['bank']),
                'inventory_value' => $this->formatCents($balances['inventory']),
                'current_assets' => $this->formatCents($balances['cash'] + $balances['bank'] + $balances['inventory']),
                'fixed_asset_value' => $this->formatCents($balances['fixed_asset']),
                'total_assets' => $this->formatCents($totalAssets),
                'known_assets_total' => $this->formatCents($totalAssets),
                'inventory_complete' => true,
                'missing_inventory_products' => [],
            ],
            'liabilities' => [
                'total' => $this->formatCents($balances['payable']),
            ],
            'equity' => [
                'capital' => $this->formatCents($balances['capital']),
                'current_profit' => $profit === null ? null : $this->formatCents($profit),
                'total' => $totalEquity === null ? null : $this->formatCents($totalEquity),
            ],
            'total_liabilities_equity' => $totalLiabilitiesEquity === null ? null : $this->formatCents($totalLiabilitiesEquity),
            'difference' => $difference === null ? null : $this->formatSignedCents($difference),
            'balanced' => $difference === 0 && $hppComplete,
            'hpp_complete' => $hppComplete,
            'missing_hpp_products' => $missingHppProducts->all(),
            'missing' => $difference === 0 && $hppComplete ? [] : array_values(array_filter([
                $hppComplete ? null : 'HPP belum lengkap untuk: '.$missingHppProducts->implode(', '),
                $difference === 0 ? null : 'Saldo akun belum seimbang.',
            ])),
            'note' => $difference === 0 && $hppComplete
                ? 'Neraca dihitung dari saldo akun ledger sampai tanggal posisi laporan.'
                : 'Neraca menampilkan saldo ledger, tetapi belum dapat dinyatakan lengkap atau seimbang.',
        ];
    }

    private function unavailable(string $date, string $note, array $missing = []): array
    {
        return [
            'available' => true,
            'accounting_active' => false,
            'accounting_complete' => false,
            'as_of_date' => $date,
            'cutover_date' => null,
            'assets' => [
                'cash' => null,
                'bank' => null,
                'cash_total' => null,
                'inventory_value' => null,
                'current_assets' => null,
                'fixed_asset_value' => null,
                'total_assets' => null,
                'known_assets_total' => null,
                'inventory_complete' => false,
                'missing_inventory_products' => [],
            ],
            'liabilities' => ['total' => null],
            'equity' => ['capital' => null, 'current_profit' => null, 'total' => null],
            'total_liabilities_equity' => null,
            'difference' => null,
            'balanced' => false,
            'hpp_complete' => false,
            'missing_hpp_products' => [],
            'missing' => $missing,
            'note' => $note,
        ];
    }

    private function missingHppProducts(int $outletId, string $cutoverDate, string $asOfDate): \Illuminate\Support\Collection
    {
        return TransactionItem::query()
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->leftJoin('products', 'transaction_items.product_id', '=', 'products.id')
            ->where('transactions.outlet_id', $outletId)
            ->where('transactions.status', 'completed')
            ->whereDate('transactions.created_at', '>=', $cutoverDate)
            ->whereDate('transactions.created_at', '<=', $asOfDate)
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

    private function formatSignedCents(int $cents): string
    {
        return $cents < 0 ? '-'.$this->formatCents(abs($cents)) : $this->formatCents($cents);
    }
}
