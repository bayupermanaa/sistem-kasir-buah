<?php

namespace App\Services;

use App\Models\JournalEntry;
use Illuminate\Support\Carbon;

class CashFlowService
{
    public function __construct(private readonly AccountingLedgerService $ledger)
    {
    }

    public function build(int $outletId, ?string $dateFrom, ?string $dateTo, array $historicalFallback = []): array
    {
        $cutover = $this->ledger->getCutoverDate($outletId);
        $openingExists = JournalEntry::query()
            ->where('outlet_id', $outletId)
            ->where('journal_type', 'opening_balance')
            ->exists();

        if (! $cutover || ! $openingExists) {
            return $this->fallback($historicalFallback, $cutover);
        }

        $requestedTo = $dateTo ? Carbon::parse($dateTo)->toDateString() : Carbon::today()->toDateString();
        if ($requestedTo < $cutover->toDateString()) {
            return $this->fallback($historicalFallback, $cutover, 'Periode berada sebelum tanggal cutover; arus kas berbasis ledger belum tersedia.');
        }

        $periodFrom = $dateFrom ? Carbon::parse($dateFrom)->toDateString() : $cutover->toDateString();
        if ($periodFrom < $cutover->toDateString()) {
            $periodFrom = $cutover->toDateString();
        }

        $entries = $this->ledger->journalsForPeriod($outletId, $periodFrom, $requestedTo)->get();
        $openingEntries = JournalEntry::query()
            ->with('lines.account')
            ->where('outlet_id', $outletId)
            ->whereDate('journal_date', '>=', $cutover->toDateString())
            ->whereDate('journal_date', '<', $periodFrom)
            ->get();

        $opening = $this->cashBalances($openingEntries);
        if ($periodFrom === $cutover->toDateString()) {
            $openingEntry = JournalEntry::query()
                ->with('lines.account')
                ->where('outlet_id', $outletId)
                ->where('journal_type', 'opening_balance')
                ->whereDate('journal_date', $periodFrom)
                ->get();
            $opening = $this->addBalances($opening, $this->cashBalances($openingEntry));
        }

        $flow = [
            'cash_in_cash' => 0, 'cash_in_qris' => 0, 'cash_in_total' => 0,
            'cash_out_expenses' => 0, 'cash_out_stock_purchases' => 0,
            'cash_out_payable_payments' => 0, 'cash_out_assets' => 0,
            'cash_out_total' => 0, 'operating_inflow' => 0,
            'operating_outflow' => 0, 'operating_net' => 0,
            'investing_net' => 0, 'financing_net' => 0, 'net_change' => 0,
        ];
        $unclassified = [];
        $internalTransfers = [];

        foreach ($entries as $entry) {
            if ($entry->journal_type === 'opening_balance') {
                continue;
            }

            $cashLines = $entry->lines->filter(fn ($line) => in_array($line->account?->subtype, ['cash', 'bank'], true));
            if ($cashLines->isEmpty()) {
                continue;
            }

            $cashAmount = $cashLines->sum(fn ($line) => $this->cents($line->debit) - $this->cents($line->credit));
            $hasBothAccounts = $cashLines->pluck('account.subtype')->unique()->count() > 1;
            if ($hasBothAccounts) {
                if ($cashAmount === 0) {
                    $internalTransfers[] = $entry->id;
                } else {
                    $unclassified[] = [
                        'id' => $entry->id,
                        'date' => $entry->journal_date->toDateString(),
                        'description' => $entry->description,
                        'amount' => $this->money(abs($cashAmount)),
                    ];
                }
                continue;
            }

            $method = $cashLines->first()->account?->subtype;
            $isInflow = $cashAmount > 0;
            $amount = abs($cashAmount);

            if ($entry->journal_type === 'sale' && $isInflow) {
                $flow['cash_in_total'] += $amount;
                $flow[$method === 'cash' ? 'cash_in_cash' : 'cash_in_qris'] += $amount;
                $flow['operating_inflow'] += $amount;
            } elseif (in_array($entry->journal_type, ['stock_purchase', 'payable_payment', 'operating_expense'], true) && ! $isInflow) {
                $key = match ($entry->journal_type) {
                    'stock_purchase' => 'cash_out_stock_purchases',
                    'payable_payment' => 'cash_out_payable_payments',
                    default => 'cash_out_expenses',
                };
                $flow[$key] += $amount;
                $flow['operating_outflow'] += $amount;
            } elseif ($entry->journal_type === 'asset_purchase' && ! $isInflow) {
                $flow['cash_out_assets'] += $amount;
                $flow['investing_net'] -= $amount;
            } elseif (in_array($entry->journal_type, ['capital_injection', 'financing'], true) && $isInflow) {
                $flow['financing_net'] += $amount;
            } else {
                $unclassified[] = [
                    'id' => $entry->id,
                    'date' => $entry->journal_date->toDateString(),
                    'description' => $entry->description,
                    'amount' => $this->money($amount),
                ];
            }
        }

        $flow['cash_out_total'] = $flow['cash_out_expenses'] + $flow['cash_out_stock_purchases'] + $flow['cash_out_payable_payments'] + $flow['cash_out_assets'];
        $flow['operating_net'] = $flow['operating_inflow'] - $flow['operating_outflow'];
        $flow['net_change'] = $flow['operating_net'] + $flow['investing_net'] + $flow['financing_net'];
        $ending = $this->cashBalances(JournalEntry::query()->with('lines.account')->where('outlet_id', $outletId)->whereDate('journal_date', '>=', $cutover->toDateString())->whereDate('journal_date', '<=', $requestedTo)->get());
        $openingTotal = $opening['cash'] + $opening['bank'];
        $endingTotal = $ending['cash'] + $ending['bank'];
        $difference = $endingTotal - ($openingTotal + $flow['net_change']);
        $formattedFlow = $flow;
        foreach (['cash_in_cash', 'cash_in_qris', 'cash_in_total', 'cash_out_expenses', 'cash_out_stock_purchases', 'cash_out_payable_payments', 'cash_out_assets', 'cash_out_total', 'operating_inflow', 'operating_outflow', 'operating_net', 'investing_net', 'financing_net', 'net_change'] as $key) {
            $formattedFlow[$key] = $this->money($flow[$key]);
        }

        return array_merge($formattedFlow, [
            'source' => 'accounting_ledger', 'accounting_active' => true, 'accounting_complete' => empty($unclassified),
            'period_from' => $periodFrom, 'period_to' => $requestedTo, 'cutover_date' => $cutover->toDateString(),
            'opening_cash' => $this->money($opening['cash']), 'opening_bank' => $this->money($opening['bank']), 'opening_total' => $this->money($openingTotal),
            'ending_cash' => $this->money($ending['cash']), 'ending_bank' => $this->money($ending['bank']), 'ending_total' => $this->money($endingTotal),
            'cash_flow_net' => $this->money($flow['net_change']), 'difference' => $this->money($difference),
            'reconciled' => empty($unclassified) && $difference === 0,
            'unclassified' => $unclassified, 'internal_transfers' => $internalTransfers,
            'expense_by_category' => collect(),
            'note' => empty($unclassified) ? null : 'Sebagian jurnal kas belum dapat diklasifikasikan ke arus kas.',
        ]);
    }

    private function fallback(array $fallback, ?Carbon $cutover, ?string $note = null): array
    {
        $get = fn (string $key): int => $this->cents($fallback[$key] ?? 0);
        $cashIn = $get('cash_in_cash');
        $qrisIn = $get('cash_in_qris');
        $expenses = $get('cash_out_expenses');
        $stock = $get('cash_out_stock_purchases');
        $assets = $get('cash_out_assets');
        $out = $expenses + $stock + $assets;

        return [
            'source' => 'historical_operational', 'accounting_active' => false, 'accounting_complete' => false,
            'cash_in_cash' => $this->money($cashIn), 'cash_in_qris' => $this->money($qrisIn), 'cash_in_total' => $this->money($cashIn + $qrisIn),
            'cash_out_expenses' => $this->money($expenses), 'cash_out_stock_purchases' => $this->money($stock), 'cash_out_payable_payments' => '0.00', 'cash_out_assets' => $this->money($assets), 'cash_out_total' => $this->money($out),
            'operating_inflow' => $this->money($cashIn + $qrisIn), 'operating_outflow' => $this->money($expenses + $stock), 'operating_net' => $this->money($cashIn + $qrisIn - $expenses - $stock), 'investing_net' => $this->money(-$assets), 'financing_net' => '0.00', 'net_change' => $this->money($cashIn + $qrisIn - $out), 'cash_flow_net' => $this->money($cashIn + $qrisIn - $out),
            'opening_cash' => null, 'opening_bank' => null, 'opening_total' => null, 'ending_cash' => null, 'ending_bank' => null, 'ending_total' => null, 'difference' => null,
            'reconciled' => false, 'unclassified' => [], 'internal_transfers' => [], 'expense_by_category' => $fallback['expense_by_category'] ?? collect(), 'cutover_date' => $cutover?->toDateString(),
            'note' => $note ?? 'Laporan arus kas ledger belum aktif; angka ini adalah ringkasan operasional historis dan belum final.',
        ];
    }

    private function cashBalances($entries): array
    {
        $balances = ['cash' => 0, 'bank' => 0];
        foreach ($entries as $entry) {
            foreach ($entry->lines as $line) {
                $subtype = $line->account?->subtype;
                if (isset($balances[$subtype])) {
                    $balances[$subtype] += $this->cents($line->debit) - $this->cents($line->credit);
                }
            }
        }
        return $balances;
    }

    private function addBalances(array $left, array $right): array
    {
        return ['cash' => $left['cash'] + $right['cash'], 'bank' => $left['bank'] + $right['bank']];
    }

    private function cents(mixed $value): int
    {
        $text = trim((string) $value);
        if ($text === '') {
            return 0;
        }
        $negative = str_starts_with($text, '-');
        $text = ltrim($text, '+-');
        [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '0');
        $cents = ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
        return $negative ? -$cents : $cents;
    }

    private function money(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);
        return $sign.sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
