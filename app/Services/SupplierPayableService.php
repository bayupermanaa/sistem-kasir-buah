<?php

namespace App\Services;

use App\Models\AccountingSetting;
use App\Models\JournalEntry;
use App\Models\Outlet;
use App\Models\PayablePayment;
use App\Models\StockPurchase;
use App\Models\Supplier;
use App\Models\SupplierPayable;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierPayableService
{
    public function __construct(
        private readonly SingleOutletService $singleOutletService,
        private readonly AccountingLedgerService $ledger,
    ) {
    }

    public function createPayable(User $user, Outlet|int $outlet, array $data): SupplierPayable
    {
        $outletId = $this->assertOwnerOutlet($user, $outlet);
        $amount = $this->parseAmount($data['original_amount'] ?? null);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['original_amount' => 'Nominal utang harus lebih besar dari nol.']);
        }

        if ($this->hasActiveAccounting($outletId)) {
            throw ValidationException::withMessages([
                'accounting' => 'Pembukuan sudah aktif. Utang baru harus berasal dari pembelian kredit yang sudah terintegrasi, bukan input manual.',
            ]);
        }

        $supplier = Supplier::query()->whereKey($data['supplier_id'] ?? 0)->where('is_active', true)->first();
        if (! $supplier) {
            throw ValidationException::withMessages(['supplier_id' => 'Supplier tidak aktif atau tidak ditemukan.']);
        }

        $sourceType = $data['source_type'] ?? null;
        $sourceId = $data['source_id'] ?? null;
        if (($sourceType === null) !== ($sourceId === null)) {
            throw ValidationException::withMessages(['source' => 'Sumber utang harus memiliki tipe dan ID secara bersamaan.']);
        }

        if ($sourceType !== null && SupplierPayable::query()
            ->where('outlet_id', $outletId)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->exists()) {
            throw ValidationException::withMessages(['source' => 'Sumber pembelian tersebut sudah memiliki utang.']);
        }

        return DB::transaction(function () use ($outletId, $supplier, $data, $amount, $sourceType, $sourceId, $user): SupplierPayable {
            return SupplierPayable::create([
            'outlet_id' => $outletId,
            'supplier_id' => $supplier->id,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'invoice_number' => $data['invoice_number'] ?? null,
            'debt_date' => Carbon::parse($data['debt_date'])->toDateString(),
            'due_date' => ! empty($data['due_date']) ? Carbon::parse($data['due_date'])->toDateString() : null,
            'original_amount' => $this->formatCents($amount),
            'paid_amount' => '0.00',
            'outstanding_amount' => $this->formatCents($amount),
            'status' => 'open',
            'description' => $data['description'] ?? null,
            'created_by' => $user->id,
            ]);
        });
    }

    public function createFromStockPurchase(User $user, StockPurchase $purchase, int $supplierId, string $dueDate): SupplierPayable
    {
        $outletId = $this->assertOwnerOutlet($user, $purchase->outlet_id);
        if (! $this->hasActiveAccounting($outletId)) {
            throw ValidationException::withMessages(['accounting' => 'Pembukuan belum aktif. Pembelian kredit belum dapat dicatat sebagai utang.']);
        }

        $cutoverDate = AccountingSetting::query()->where('outlet_id', $outletId)->value('cutover_date');
        if (Carbon::parse($purchase->purchase_date)->lt(Carbon::parse($cutoverDate))) {
            throw ValidationException::withMessages(['purchase_date' => 'Pembelian tidak boleh dicatat sebelum tanggal cutover.']);
        }

        $supplier = Supplier::query()->whereKey($supplierId)->where('is_active', true)->first();
        if (! $supplier) {
            throw ValidationException::withMessages(['supplier_id' => 'Supplier tidak aktif atau tidak ditemukan.']);
        }

        if (SupplierPayable::query()
            ->where('outlet_id', $outletId)
            ->where('source_type', StockPurchase::class)
            ->where('source_id', $purchase->id)
            ->exists()) {
            throw ValidationException::withMessages(['source' => 'Pembelian stok tersebut sudah memiliki utang.']);
        }

        $amount = $this->parseAmount($purchase->total_cost);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['unit_cost' => 'Harga beli wajib diisi untuk pembelian kredit.']);
        }

        return SupplierPayable::create([
            'outlet_id' => $outletId,
            'supplier_id' => $supplier->id,
            'source_type' => StockPurchase::class,
            'source_id' => $purchase->id,
            'invoice_number' => $purchase->invoice_number,
            'debt_date' => $purchase->purchase_date,
            'due_date' => $dueDate,
            'original_amount' => $this->formatCents($amount),
            'paid_amount' => '0.00',
            'outstanding_amount' => $this->formatCents($amount),
            'status' => 'open',
            'description' => 'Utang pembelian stok',
            'created_by' => $user->id,
        ]);
    }

    public function recordStockPurchaseJournal(User $user, StockPurchase $purchase, string $creditSubtype): JournalEntry
    {
        $outletId = $this->assertOwnerOutlet($user, $purchase->outlet_id);
        if (! $this->hasActiveAccounting($outletId)) {
            throw ValidationException::withMessages(['accounting' => 'Pembukuan belum aktif.']);
        }

        $cutoverDate = AccountingSetting::query()->where('outlet_id', $outletId)->value('cutover_date');
        if (Carbon::parse($purchase->purchase_date)->lt(Carbon::parse($cutoverDate))) {
            throw ValidationException::withMessages(['purchase_date' => 'Pembelian tidak boleh dicatat sebelum tanggal cutover.']);
        }

        if (! in_array($creditSubtype, ['cash', 'bank', 'payable'], true)) {
            throw ValidationException::withMessages(['payment_method' => 'Metode pembayaran tidak memiliki akun jurnal yang valid.']);
        }

        $amount = $this->parseAmount($purchase->total_cost);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['unit_cost' => 'Harga beli wajib diisi saat pembukuan aktif.']);
        }

        return $this->ledger->createJournal(
            outlet: $outletId,
            journalDate: $purchase->purchase_date,
            journalType: 'stock_purchase',
            description: $creditSubtype === 'payable' ? 'Pembelian stok kredit' : 'Pembelian stok tunai/non-tunai',
            lines: [
                ['account_id' => $this->accountId('inventory'), 'debit' => $this->formatCents($amount), 'credit' => '0.00'],
                ['account_id' => $this->accountId($creditSubtype), 'debit' => '0.00', 'credit' => $this->formatCents($amount)],
            ],
            createdBy: $user,
            referenceType: StockPurchase::class,
            referenceId: $purchase->id,
        );
    }

    public function pay(User $user, SupplierPayable|int $payable, array $data): array
    {
        $payableId = $payable instanceof SupplierPayable ? $payable->id : (int) $payable;
        $paymentAmount = $this->parseAmount($data['amount'] ?? null);
        if ($paymentAmount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Pembayaran harus lebih besar dari nol.']);
        }

        return DB::transaction(function () use ($user, $payableId, $paymentAmount, $data): array {
            $lockedPayable = SupplierPayable::query()
                ->whereKey($payableId)
                ->where('outlet_id', $this->singleOutletService->getDefaultOutletId())
                ->lockForUpdate()
                ->first();
            if (! $lockedPayable) {
                abort(404);
            }
            if (PayablePayment::query()->where('idempotency_key', $data['idempotency_key'])->exists()) {
                throw ValidationException::withMessages(['idempotency_key' => 'Pembayaran yang sama sudah diproses.']);
            }
            if ($lockedPayable->status === 'paid' || $this->parseAmount($lockedPayable->outstanding_amount) <= 0) {
                throw ValidationException::withMessages(['amount' => 'Utang ini sudah lunas.']);
            }

            $outstanding = $this->parseAmount($lockedPayable->outstanding_amount);
            if ($paymentAmount > $outstanding) {
                throw ValidationException::withMessages(['amount' => 'Pembayaran tidak boleh melebihi sisa utang.']);
            }

            $paid = $this->parseAmount($lockedPayable->paid_amount) + $paymentAmount;
            $remaining = $outstanding - $paymentAmount;
            $payment = PayablePayment::create([
                'supplier_payable_id' => $lockedPayable->id,
                'outlet_id' => $lockedPayable->outlet_id,
                'payment_date' => Carbon::parse($data['payment_date'])->toDateString(),
                'amount' => $this->formatCents($paymentAmount),
                'payment_method' => $data['payment_method'],
                'idempotency_key' => $data['idempotency_key'],
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $lockedPayable->paid_amount = $this->formatCents($paid);
            $lockedPayable->outstanding_amount = $this->formatCents($remaining);
            $lockedPayable->status = $remaining === 0 ? 'paid' : 'partial';
            $lockedPayable->save();

            $journaled = false;
            if ($this->hasActiveAccounting($lockedPayable->outlet_id)) {
                $cutoverDate = AccountingSetting::query()->where('outlet_id', $lockedPayable->outlet_id)->value('cutover_date');
                if (Carbon::parse($data['payment_date'])->lt(Carbon::parse($cutoverDate))) {
                    throw ValidationException::withMessages(['payment_date' => 'Pembayaran tidak boleh dijurnal sebelum tanggal cutover.']);
                }

                $payableAccount = $this->accountId('payable');
                $cashAccount = $this->accountId(in_array($data['payment_method'], ['cash'], true) ? 'cash' : 'bank');
                $this->ledger->createJournal(
                    outlet: $lockedPayable->outlet_id,
                    journalDate: $data['payment_date'],
                    journalType: 'payable_payment',
                    description: 'Pembayaran utang supplier',
                    lines: [
                        ['account_id' => $payableAccount, 'debit' => $this->formatCents($paymentAmount), 'credit' => '0.00'],
                        ['account_id' => $cashAccount, 'debit' => '0.00', 'credit' => $this->formatCents($paymentAmount)],
                    ],
                    createdBy: $user,
                    referenceType: PayablePayment::class,
                    referenceId: $payment->id,
                );
                $journaled = true;
            } elseif (AccountingSetting::query()->where('outlet_id', $lockedPayable->outlet_id)->whereNotNull('cutover_date')->exists()) {
                throw ValidationException::withMessages(['accounting' => 'Saldo pembukaan belum diposting. Pembayaran belum dapat dicatat ke pembukuan aktif.']);
            }

            return ['payment' => $payment->fresh(), 'payable' => $lockedPayable->fresh(['supplier', 'payments']), 'journaled' => $journaled];
        });
    }

    public function hasActiveAccounting(int $outletId): bool
    {
        return AccountingSetting::query()->where('outlet_id', $outletId)->whereNotNull('cutover_date')->exists()
            && JournalEntry::query()->where('outlet_id', $outletId)->where('journal_type', 'opening_balance')->exists();
    }

    public function cutoverDate(int $outletId): ?Carbon
    {
        $value = AccountingSetting::query()->where('outlet_id', $outletId)->value('cutover_date');

        return $value ? Carbon::parse($value) : null;
    }

    public function totalsToMinorUnits(SupplierPayable $payable): array
    {
        return [
            'original' => $this->parseAmount($payable->original_amount),
            'paid' => $this->parseAmount($payable->paid_amount),
            'outstanding' => $this->parseAmount($payable->outstanding_amount),
        ];
    }

    private function assertOwnerOutlet(User $user, Outlet|int $outlet): int
    {
        $outletId = $outlet instanceof Outlet ? (int) $outlet->id : (int) $outlet;
        abort_unless($user->role === 'owner' && $outletId === $this->singleOutletService->getDefaultOutletId(), 403);
        return $outletId;
    }

    private function accountId(string $subtype): int
    {
        $account = \App\Models\AccountingAccount::query()->where('subtype', $subtype)->where('is_active', true)->first();
        if (! $account) {
            throw ValidationException::withMessages(['accounting' => "Akun {$subtype} belum tersedia atau tidak aktif."]);
        }
        return (int) $account->id;
    }

    private function parseAmount(mixed $value): int
    {
        if ($value === null || $value === '') return 0;
        $text = is_float($value) ? number_format($value, 2, '.', '') : trim((string) $value);
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/', $text)) {
            throw ValidationException::withMessages(['amount' => 'Nominal harus non-negatif dengan maksimal dua desimal.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '0');
        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function formatCents(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
