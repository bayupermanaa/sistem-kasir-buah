<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\AccountingSetting;
use App\Models\JournalEntry;
use App\Models\Outlet;
use App\Services\AccountingLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountingLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function outlet(string $name = 'Toko Akuntansi'): Outlet
    {
        return Outlet::create(['name' => $name, 'address' => null, 'phone' => null]);
    }

    private function accounts(): array
    {
        return [
            'cash' => AccountingAccount::create(['code' => '1000', 'name' => 'Kas Tunai', 'type' => 'asset', 'subtype' => 'cash', 'is_active' => true]),
            'capital' => AccountingAccount::create(['code' => '3000', 'name' => 'Modal Pemilik', 'type' => 'equity', 'subtype' => 'capital', 'is_active' => true]),
        ];
    }

    public function test_balanced_journal_is_accepted_and_account_balance_is_correct(): void
    {
        $outlet = $this->outlet();
        $accounts = $this->accounts();
        $service = app(AccountingLedgerService::class);

        $entry = $service->createJournal($outlet, '2026-10-20', 'opening_balance', 'Saldo pembukaan', [
            ['account_id' => $accounts['cash']->id, 'debit' => '10000000.00', 'credit' => '0.00'],
            ['account_id' => $accounts['capital']->id, 'debit' => '0.00', 'credit' => '10000000.00'],
        ]);

        $this->assertCount(2, $entry->lines);
        $this->assertSame('10000000.00', $service->accountBalance($accounts['cash']->id, $outlet->id)['balance']);
    }

    public function test_single_line_journal_is_rejected(): void
    {
        $outlet = $this->outlet();
        $accounts = $this->accounts();
        $this->expectException(ValidationException::class);
        app(AccountingLedgerService::class)->createJournal($outlet, '2026-10-20', 'test', 'Invalid', [
            ['account_id' => $accounts['cash']->id, 'debit' => '10.00', 'credit' => '0.00'],
        ]);
    }

    public function test_mixed_debit_and_credit_line_is_rejected(): void
    {
        $outlet = $this->outlet();
        $accounts = $this->accounts();
        $this->expectException(ValidationException::class);
        app(AccountingLedgerService::class)->createJournal($outlet, '2026-10-20', 'test', 'Invalid', [
            ['account_id' => $accounts['cash']->id, 'debit' => '10.00', 'credit' => '1.00'],
            ['account_id' => $accounts['capital']->id, 'debit' => '0.00', 'credit' => '9.00'],
        ]);
    }

    public function test_unbalanced_journal_is_rejected(): void
    {
        $outlet = $this->outlet();
        $accounts = $this->accounts();
        $this->expectException(ValidationException::class);
        app(AccountingLedgerService::class)->createJournal($outlet, '2026-10-20', 'test', 'Invalid', [
            ['account_id' => $accounts['cash']->id, 'debit' => '10.00', 'credit' => '0.00'],
            ['account_id' => $accounts['capital']->id, 'debit' => '0.00', 'credit' => '9.00'],
        ]);
    }

    public function test_negative_amount_is_rejected(): void
    {
        $outlet = $this->outlet();
        $accounts = $this->accounts();
        $this->expectException(ValidationException::class);
        app(AccountingLedgerService::class)->createJournal($outlet, '2026-10-20', 'test', 'Invalid', [
            ['account_id' => $accounts['cash']->id, 'debit' => '-10.00', 'credit' => '0.00'],
            ['account_id' => $accounts['capital']->id, 'debit' => '0.00', 'credit' => '10.00'],
        ]);
    }
    public function test_negative_amount_duplicate_reference_and_inactive_account_are_rejected(): void
    {
        $outlet = $this->outlet();
        $accounts = $this->accounts();
        $service = app(AccountingLedgerService::class);
        $lines = [
            ['account_id' => $accounts['cash']->id, 'debit' => '10.00', 'credit' => '0.00'],
            ['account_id' => $accounts['capital']->id, 'debit' => '0.00', 'credit' => '10.00'],
        ];

        $service->createJournal($outlet, '2026-10-20', 'opening_balance', 'Saldo pembukaan', $lines, null, 'OpeningBalance', 1);
        $this->expectException(ValidationException::class);
        $service->createJournal($outlet, '2026-10-20', 'opening_balance', 'Duplikat', $lines, null, 'OpeningBalance', 1);
    }

    public function test_inactive_account_is_rejected(): void
    {
        $outlet = $this->outlet();
        $accounts = $this->accounts();
        $accounts['capital']->update(['is_active' => false]);
        $this->expectException(ValidationException::class);
        app(AccountingLedgerService::class)->createJournal($outlet, '2026-10-20', 'test', 'Invalid', [
            ['account_id' => $accounts['cash']->id, 'debit' => '10.00', 'credit' => '0.00'],
            ['account_id' => $accounts['capital']->id, 'debit' => '0.00', 'credit' => '10.00'],
        ]);
    }

    public function test_account_balance_isolated_by_outlet(): void
    {
        $first = $this->outlet('Toko Satu');
        $second = $this->outlet('Toko Dua');
        $accounts = $this->accounts();
        $service = app(AccountingLedgerService::class);
        $service->createJournal($first, '2026-10-20', 'opening_balance', 'Saldo pembukaan', [
            ['account_id' => $accounts['cash']->id, 'debit' => '10.00', 'credit' => '0.00'],
            ['account_id' => $accounts['capital']->id, 'debit' => '0.00', 'credit' => '10.00'],
        ]);

        $this->assertSame('10.00', $service->accountBalance($accounts['cash']->id, $first->id)['balance']);
        $this->assertSame('0.00', $service->accountBalance($accounts['cash']->id, $second->id)['balance']);
    }
    public function test_cutover_is_not_set_until_explicitly_configured_and_outlet_isolated(): void
    {
        $first = $this->outlet('Toko Satu');
        $second = $this->outlet('Toko Dua');
        $service = app(AccountingLedgerService::class);

        $this->assertNull($service->getCutoverDate($first->id));
        $service->setCutoverDate($first->id, '2026-12-01');
        $this->assertSame('2026-12-01', $service->getCutoverDate($first->id)?->toDateString());
        $this->assertNull($service->getCutoverDate($second->id));
        $this->assertSame('2026-12-01', AccountingSetting::where('outlet_id', $first->id)->firstOrFail()->cutover_date->toDateString());
    }

    public function test_existing_transaction_tables_are_not_changed_by_ledger_operations(): void
    {
        $outlet = $this->outlet();
        $accounts = $this->accounts();
        $service = app(AccountingLedgerService::class);

        $service->createJournal($outlet, '2026-10-20', 'opening_balance', 'Saldo pembukaan', [
            ['account_id' => $accounts['cash']->id, 'debit' => '10.00', 'credit' => '0.00'],
            ['account_id' => $accounts['capital']->id, 'debit' => '0.00', 'credit' => '10.00'],
        ]);

        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('transaction_items', 0);
    }
}
