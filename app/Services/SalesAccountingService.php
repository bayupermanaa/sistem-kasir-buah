<?php

namespace App\Services;

use App\Models\AccountingSetting;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SalesAccountingService
{
    public function __construct(private readonly AccountingLedgerService $ledger)
    {
    }

    public function recordSale(Transaction $transaction): ?\App\Models\JournalEntry
    {
        $setting = AccountingSetting::query()->where('outlet_id', $transaction->outlet_id)->first();
        if (! $setting?->cutover_date || ! $this->openingBalanceExists($transaction->outlet_id)) {
            return null;
        }

        $transactionDate = $transaction->created_at?->toDateString() ?? now()->toDateString();
        if (Carbon::parse($transactionDate)->lt(Carbon::parse($setting->cutover_date))) {
            return null;
        }

        $transaction->loadMissing('items.product');
        $total = $this->parseAmount($transaction->total);
        if ($total <= 0) {
            throw ValidationException::withMessages(['transaction' => 'Total transaksi tidak valid untuk jurnal.']);
        }

        $paymentAccount = $this->accountId($transaction->payment_method === 'cash' ? 'cash' : 'bank');
        $salesAccount = $this->accountId('sales');
        $lines = [
            ['account_id' => $paymentAccount, 'debit' => $this->formatCents($total), 'credit' => '0.00', 'description' => 'Penerimaan penjualan'],
            ['account_id' => $salesAccount, 'debit' => '0.00', 'credit' => $this->formatCents($total), 'description' => 'Penjualan bersih'],
        ];

        $hppCents = $this->calculateHpp($transaction);
        if ($hppCents !== null && $hppCents > 0) {
            $lines[] = ['account_id' => $this->accountId('cost_of_goods_sold'), 'debit' => $this->formatCents($hppCents), 'credit' => '0.00', 'description' => 'HPP penjualan'];
            $lines[] = ['account_id' => $this->accountId('inventory'), 'debit' => '0.00', 'credit' => $this->formatCents($hppCents), 'description' => 'Pengurangan persediaan'];
        }

        return $this->ledger->createJournal(
            outlet: $transaction->outlet_id,
            journalDate: $transactionDate,
            journalType: 'sale',
            description: 'Penjualan '.$transaction->invoice_number,
            lines: $lines,
            createdBy: $transaction->user,
            referenceType: Transaction::class,
            referenceId: $transaction->id,
        );
    }

    private function calculateHpp(Transaction $transaction): ?int
    {
        if ($transaction->items->contains(fn (TransactionItem $item): bool => $item->product?->purchase_price === null)) {
            return null;
        }

        $total = 0;
        foreach ($transaction->items as $item) {
            $quantityThousand = $this->parseQuantity($item->quantity);
            $purchasePriceCents = $this->parseAmount($item->product->purchase_price);
            $total += intdiv(($quantityThousand * $purchasePriceCents) + 500, 1000);
        }

        return $total;
    }

    private function openingBalanceExists(int $outletId): bool
    {
        return \App\Models\JournalEntry::query()->where('outlet_id', $outletId)->where('journal_type', 'opening_balance')->exists();
    }

    private function accountId(string $subtype): int
    {
        $account = \App\Models\AccountingAccount::query()->where('subtype', $subtype)->where('is_active', true)->first();
        if (! $account) {
            throw ValidationException::withMessages(['accounting' => "Akun {$subtype} belum tersedia atau tidak aktif."]);
        }

        return (int) $account->id;
    }

    private function parseQuantity(mixed $value): int
    {
        $text = trim((string) $value);
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,3})?$/', $text)) {
            throw ValidationException::withMessages(['quantity' => 'Quantity transaksi tidak valid.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '0');

        return ((int) $whole * 1000) + (int) str_pad($fraction, 3, '0');
    }

    private function parseAmount(mixed $value): int
    {
        $text = trim((string) $value);
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/', $text)) {
            throw ValidationException::withMessages(['amount' => 'Nominal jurnal tidak valid.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function formatCents(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
