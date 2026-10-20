<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\AccountingSetting;
use App\Models\Expense;
use App\Models\JournalEntry;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ExpenseAccountingService
{
    public function __construct(private readonly AccountingLedgerService $ledger)
    {
    }

    public function recordExpense(Expense $expense): ?JournalEntry
    {
        $setting = AccountingSetting::query()->where('outlet_id', $expense->outlet_id)->first();
        if (! $setting?->cutover_date || ! $this->openingBalanceExists($expense->outlet_id)) {
            return null;
        }

        if (Carbon::parse($expense->expense_date)->lt(Carbon::parse($setting->cutover_date))) {
            throw ValidationException::withMessages(['expense_date' => 'Pengeluaran tidak boleh dicatat sebelum tanggal cutover.']);
        }
        if (! in_array($expense->payment_method, ['cash', 'transfer', 'qris'], true)) {
            throw ValidationException::withMessages(['payment_method' => 'Metode pembayaran wajib dipilih saat pembukuan aktif.']);
        }

        $amount = $this->parseAmount($expense->amount);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Nominal pengeluaran tidak valid untuk jurnal.']);
        }

        return $this->ledger->createJournal(
            outlet: $expense->outlet_id,
            journalDate: $expense->expense_date,
            journalType: 'operating_expense',
            description: 'Pengeluaran operasional: '.$expense->description,
            lines: [
                ['account_id' => $this->accountId('operating_expense'), 'debit' => $this->formatCents($amount), 'credit' => '0.00', 'description' => 'Beban operasional'],
                ['account_id' => $this->accountId($expense->payment_method === 'cash' ? 'cash' : 'bank'), 'debit' => '0.00', 'credit' => $this->formatCents($amount), 'description' => 'Kas/bank keluar'],
            ],
            createdBy: $expense->creator,
            referenceType: Expense::class,
            referenceId: $expense->id,
        );
    }

    public function hasJournal(Expense $expense): bool
    {
        return JournalEntry::query()->where('outlet_id', $expense->outlet_id)
            ->where('reference_type', Expense::class)->where('reference_id', $expense->id)
            ->where('journal_type', 'operating_expense')->exists();
    }

    private function openingBalanceExists(int $outletId): bool
    {
        return JournalEntry::query()->where('outlet_id', $outletId)->where('journal_type', 'opening_balance')->exists();
    }

    private function accountId(string $subtype): int
    {
        $account = AccountingAccount::query()->where('subtype', $subtype)->where('is_active', true)->first();
        if (! $account) {
            throw ValidationException::withMessages(['accounting' => "Akun {$subtype} belum tersedia atau tidak aktif."]);
        }
        return (int) $account->id;
    }

    private function parseAmount(mixed $value): int
    {
        $text = trim((string) $value);
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/', $text)) {
            throw ValidationException::withMessages(['amount' => 'Nominal pengeluaran harus berupa angka dengan maksimal dua desimal.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '0');
        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function formatCents(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
