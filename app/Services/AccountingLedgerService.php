<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\AccountingSetting;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingLedgerService
{
    public function createJournal(
        Outlet|int $outlet,
        string|Carbon $journalDate,
        string $journalType,
        string $description,
        array $lines,
        ?User $createdBy = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): JournalEntry {
        $outletId = $outlet instanceof Outlet ? (int) $outlet->id : (int) $outlet;
        if ($outletId < 1 || ! Outlet::query()->whereKey($outletId)->exists()) {
            throw ValidationException::withMessages(['outlet_id' => 'Outlet jurnal tidak valid.']);
        }

        $date = $journalDate instanceof Carbon ? $journalDate->toDateString() : Carbon::parse($journalDate)->toDateString();
        $normalizedLines = $this->normalizeLines($lines);

        return DB::transaction(function () use ($outletId, $date, $journalType, $description, $normalizedLines, $createdBy, $referenceType, $referenceId): JournalEntry {
            $cutoverDate = AccountingSetting::query()->where('outlet_id', $outletId)->value('cutover_date');
            if ($journalType !== 'opening_balance' && $cutoverDate && $date < Carbon::parse($cutoverDate)->toDateString()) {
                throw ValidationException::withMessages(['journal_date' => 'Tanggal jurnal tidak boleh sebelum tanggal cutover.']);
            }

            if ($referenceType !== null && $referenceId !== null && JournalEntry::query()
                ->where('outlet_id', $outletId)
                ->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->where('journal_type', $journalType)
                ->exists()) {
                throw ValidationException::withMessages(['reference' => 'Jurnal untuk referensi tersebut sudah dibuat.']);
            }

            $entry = JournalEntry::create([
                'outlet_id' => $outletId,
                'journal_date' => $date,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'journal_type' => $journalType,
                'description' => $description,
                'created_by' => $createdBy?->id,
            ]);

            foreach ($normalizedLines as $line) {
                $entry->lines()->create($line);
            }

            return $entry->load('lines.account');
        });
    }

    public function accountBalance(int $accountId, int $outletId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = JournalLine::query()
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_lines.accounting_account_id', $accountId)
            ->where('journal_entries.outlet_id', $outletId)
            ->when($dateFrom, fn (Builder $q) => $q->whereDate('journal_entries.journal_date', '>=', $dateFrom))
            ->when($dateTo, fn (Builder $q) => $q->whereDate('journal_entries.journal_date', '<=', $dateTo));

        $debit = $this->parseAmount((string) ($query->clone()->sum('journal_lines.debit')));
        $credit = $this->parseAmount((string) ($query->clone()->sum('journal_lines.credit')));

        return [
            'debit' => $this->formatCents($debit),
            'credit' => $this->formatCents($credit),
            'balance' => $this->formatSignedCents($debit - $credit),
        ];
    }

    public function journalsForPeriod(int $outletId, string $dateFrom, string $dateTo): Builder
    {
        return JournalEntry::query()
            ->with('lines.account')
            ->where('outlet_id', $outletId)
            ->whereBetween('journal_date', [$dateFrom, $dateTo])
            ->orderBy('journal_date')
            ->orderBy('id');
    }

    public function getCutoverDate(int $outletId): ?Carbon
    {
        $value = AccountingSetting::query()->where('outlet_id', $outletId)->value('cutover_date');

        return $value ? Carbon::parse($value) : null;
    }

    public function setCutoverDate(int $outletId, ?string $cutoverDate): AccountingSetting
    {
        if (! Outlet::query()->whereKey($outletId)->exists()) {
            throw ValidationException::withMessages(['outlet_id' => 'Outlet tidak valid.']);
        }

        return AccountingSetting::updateOrCreate(
            ['outlet_id' => $outletId],
            ['cutover_date' => $cutoverDate]
        );
    }

    private function normalizeLines(array $lines): array
    {
        if (count($lines) < 2) {
            throw ValidationException::withMessages(['lines' => 'Jurnal minimal memiliki dua baris.']);
        }

        $normalized = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($lines as $index => $line) {
            $accountId = (int) ($line['account_id'] ?? $line['accounting_account_id'] ?? 0);
            $debit = $this->parseAmount($line['debit'] ?? 0);
            $credit = $this->parseAmount($line['credit'] ?? 0);

            if ($accountId < 1 || ! AccountingAccount::query()->whereKey($accountId)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(["lines.$index.account_id" => 'Akun jurnal tidak valid atau tidak aktif.']);
            }
            if ($debit > 0 && $credit > 0) {
                throw ValidationException::withMessages(["lines.$index" => 'Satu baris jurnal tidak boleh memiliki debit dan kredit sekaligus.']);
            }
            if ($debit === 0 && $credit === 0) {
                throw ValidationException::withMessages(["lines.$index" => 'Nominal jurnal harus lebih besar dari nol.']);
            }

            $totalDebit += $debit;
            $totalCredit += $credit;
            $normalized[] = [
                'accounting_account_id' => $accountId,
                'debit' => $this->formatCents($debit),
                'credit' => $this->formatCents($credit),
                'description' => $line['description'] ?? null,
            ];
        }

        if ($totalDebit !== $totalCredit) {
            throw ValidationException::withMessages(['lines' => 'Total debit dan kredit harus sama.']);
        }

        return $normalized;
    }

    private function parseAmount(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $text = is_float($value) ? number_format($value, 2, '.', '') : trim((string) $value);
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/', $text)) {
            throw ValidationException::withMessages(['amount' => 'Nominal harus berupa angka non-negatif dengan maksimal dua desimal.']);
        }

        [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function formatCents(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    private function formatSignedCents(int $cents): string
    {
        if ($cents < 0) {
            return '-'.$this->formatCents(abs($cents));
        }

        return $this->formatCents($cents);
    }
}
