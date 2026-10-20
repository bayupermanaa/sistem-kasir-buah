<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\AccountingSetting;
use App\Models\Asset;
use App\Models\JournalEntry;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AssetAccountingService
{
    public function __construct(private readonly AccountingLedgerService $ledger)
    {
    }

    public function recordAsset(Asset $asset): ?JournalEntry
    {
        $setting = AccountingSetting::query()->where('outlet_id', $asset->outlet_id)->first();
        if (! $setting?->cutover_date || ! $this->openingBalanceExists($asset->outlet_id)) {
            return null;
        }

        $purchaseDate = Carbon::parse($asset->purchase_date);
        if ($purchaseDate->lt(Carbon::parse($setting->cutover_date))) {
            return null;
        }

        if (! in_array($asset->payment_method, ['cash', 'transfer', 'qris'], true)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Metode pembayaran aset wajib berupa tunai, transfer, atau QRIS saat pembukuan aktif.',
            ]);
        }

        $amount = $this->parseAmount($asset->total_cost);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['total_cost' => 'Total biaya aset tidak valid untuk jurnal.']);
        }

        return $this->ledger->createJournal(
            outlet: $asset->outlet_id,
            journalDate: $asset->purchase_date,
            journalType: 'asset_purchase',
            description: 'Pembelian aset: '.$asset->name,
            lines: [
                [
                    'account_id' => $this->accountId('fixed_asset'),
                    'debit' => $this->formatCents($amount),
                    'credit' => '0.00',
                    'description' => 'Penambahan aset tetap',
                ],
                [
                    'account_id' => $this->accountId($asset->payment_method === 'cash' ? 'cash' : 'bank'),
                    'debit' => '0.00',
                    'credit' => $this->formatCents($amount),
                    'description' => 'Pembayaran aset',
                ],
            ],
            createdBy: $asset->creator,
            referenceType: Asset::class,
            referenceId: $asset->id,
        );
    }

    public function hasJournal(Asset $asset): bool
    {
        return JournalEntry::query()
            ->where('outlet_id', $asset->outlet_id)
            ->where('reference_type', Asset::class)
            ->where('reference_id', $asset->id)
            ->where('journal_type', 'asset_purchase')
            ->exists();
    }

    private function openingBalanceExists(int $outletId): bool
    {
        return JournalEntry::query()
            ->where('outlet_id', $outletId)
            ->where('journal_type', 'opening_balance')
            ->exists();
    }

    private function accountId(string $subtype): int
    {
        $account = AccountingAccount::query()
            ->where('subtype', $subtype)
            ->where('is_active', true)
            ->first();

        if (! $account) {
            throw ValidationException::withMessages([
                'accounting' => "Akun {$subtype} belum tersedia atau tidak aktif.",
            ]);
        }

        return (int) $account->id;
    }

    private function parseAmount(mixed $value): int
    {
        $text = trim((string) $value);
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/', $text)) {
            throw ValidationException::withMessages([
                'total_cost' => 'Total biaya aset harus berupa angka dengan maksimal dua desimal.',
            ]);
        }

        [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function formatCents(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
