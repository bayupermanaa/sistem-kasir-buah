<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\AccountingSetting;
use App\Models\JournalEntry;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpeningBalanceService
{
    private const ACCOUNT_SUBTYPES = [
        'cash_balance' => 'cash',
        'bank_balance' => 'bank',
        'inventory_balance' => 'inventory',
        'fixed_asset_balance' => 'fixed_asset',
        'liabilities_balance' => 'payable',
        'capital_balance' => 'capital',
    ];

    public function __construct(
        private readonly AccountingLedgerService $ledger,
        private readonly SingleOutletService $singleOutletService,
    ) {
    }

    public function post(User $user, Outlet|int $outlet, string|Carbon|null $cutoverDate, array $balances): JournalEntry
    {
        $outletId = $outlet instanceof Outlet ? (int) $outlet->id : (int) $outlet;
        if ($user->role !== 'owner' || $outletId !== $this->singleOutletService->getDefaultOutletId()) {
            abort(403);
        }
        if (! $cutoverDate) {
            throw ValidationException::withMessages(['cutover_date' => 'Tanggal mulai pembukuan wajib ditetapkan.']);
        }

        $date = $cutoverDate instanceof Carbon ? $cutoverDate->toDateString() : Carbon::parse($cutoverDate)->toDateString();
        $amounts = $this->normalizeBalances($balances);
        $debitTotal = array_sum(array_slice($amounts, 0, 4));
        $creditTotal = $amounts['liabilities_balance'] + $amounts['capital_balance'];

        if ($debitTotal !== $creditTotal) {
            throw ValidationException::withMessages(['balances' => 'Total debit dan kredit saldo awal harus sama.']);
        }

        return DB::transaction(function () use ($user, $outletId, $date, $amounts): JournalEntry {
            $outletModel = Outlet::query()->whereKey($outletId)->lockForUpdate()->first();
            if (! $outletModel) {
                throw ValidationException::withMessages(['outlet_id' => 'Outlet saldo awal tidak valid.']);
            }

            $setting = AccountingSetting::query()->where('outlet_id', $outletId)->lockForUpdate()->first();
            if ($setting?->cutover_date && $setting->cutover_date->toDateString() !== $date) {
                throw ValidationException::withMessages(['cutover_date' => 'Tanggal cutover sudah ditetapkan dan tidak boleh diubah.']);
            }

            if (JournalEntry::query()->where('outlet_id', $outletId)->where('journal_type', 'opening_balance')->exists()) {
                throw ValidationException::withMessages(['opening_balance' => 'Saldo pembukaan untuk outlet ini sudah diposting.']);
            }

            $setting ??= AccountingSetting::create(['outlet_id' => $outletId, 'cutover_date' => $date]);
            $setting->cutover_date = $date;
            $setting->save();

            $accounts = AccountingAccount::query()
                ->whereIn('subtype', array_values(self::ACCOUNT_SUBTYPES))
                ->where('is_active', true)
                ->get()
                ->keyBy('subtype');

            $lines = [];
            foreach (self::ACCOUNT_SUBTYPES as $field => $subtype) {
                if ($amounts[$field] === 0) {
                    continue;
                }

                $account = $accounts->get($subtype);
                if (! $account) {
                    throw ValidationException::withMessages(['balances' => "Akun {$subtype} belum tersedia atau tidak aktif."]);
                }

                $isDebit = in_array($field, ['cash_balance', 'bank_balance', 'inventory_balance', 'fixed_asset_balance'], true);
                $lines[] = [
                    'account_id' => $account->id,
                    'debit' => $isDebit ? $this->formatCents($amounts[$field]) : '0.00',
                    'credit' => $isDebit ? '0.00' : $this->formatCents($amounts[$field]),
                    'description' => 'Saldo awal '.$account->name,
                ];
            }

            return $this->ledger->createJournal(
                outlet: $outletModel,
                journalDate: $date,
                journalType: 'opening_balance',
                description: 'Saldo pembukaan akuntansi',
                lines: $lines,
                createdBy: $user,
                referenceType: AccountingSetting::class,
                referenceId: $setting->id,
            );
        });
    }

    public function openingEntry(int $outletId): ?JournalEntry
    {
        return JournalEntry::query()
            ->with('lines.account')
            ->where('outlet_id', $outletId)
            ->where('journal_type', 'opening_balance')
            ->first();
    }

    public function totals(array $balances): array
    {
        $amounts = $this->normalizeBalances($balances);
        $debit = array_sum(array_slice($amounts, 0, 4));
        $credit = $amounts['liabilities_balance'] + $amounts['capital_balance'];

        return [
            'debit' => $this->formatCents($debit),
            'credit' => $this->formatCents($credit),
            'difference' => $this->formatSignedCents($debit - $credit),
        ];
    }

    private function normalizeBalances(array $balances): array
    {
        $result = [];
        foreach (array_keys(self::ACCOUNT_SUBTYPES) as $field) {
            $result[$field] = $this->parseAmount($balances[$field] ?? 0);
        }

        return $result;
    }

    private function parseAmount(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $text = is_float($value) ? number_format($value, 2, '.', '') : trim((string) $value);
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/', $text)) {
            throw ValidationException::withMessages(['balances' => 'Nominal saldo awal harus non-negatif dengan maksimal dua desimal.']);
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
        return $cents < 0 ? '-'.$this->formatCents(abs($cents)) : $this->formatCents($cents);
    }
}
