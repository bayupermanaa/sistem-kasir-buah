<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\JournalEntry;
use App\Models\Outlet;
use App\Models\User;
use App\Services\AccountingLedgerService;
use App\Services\CashFlowService;
use App\Services\OpeningBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashFlowLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function owner(?Outlet $outlet = null): User
    {
        $outlet ??= Outlet::firstOrCreate(['name' => Outlet::DEFAULT_NAME]);

        return User::factory()->create([
            'name' => 'Owner Arus Kas',
            'username' => 'owner-cashflow-'.Str::random(8),
            'email' => Str::random(8).'@example.test',
            'role' => 'owner',
            'outlet_id' => $outlet->id,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
    }

    private function activate(User $owner): void
    {
        foreach ([
            ['code' => '1000', 'name' => 'Kas Tunai', 'type' => 'asset', 'subtype' => 'cash'],
            ['code' => '1010', 'name' => 'Bank/QRIS', 'type' => 'asset', 'subtype' => 'bank'],
            ['code' => '1100', 'name' => 'Persediaan', 'type' => 'asset', 'subtype' => 'inventory'],
            ['code' => '1200', 'name' => 'Aset Tetap', 'type' => 'asset', 'subtype' => 'fixed_asset'],
            ['code' => '2000', 'name' => 'Utang Supplier', 'type' => 'liability', 'subtype' => 'payable'],
            ['code' => '3000', 'name' => 'Modal Pemilik', 'type' => 'equity', 'subtype' => 'capital'],
            ['code' => '4000', 'name' => 'Penjualan', 'type' => 'revenue', 'subtype' => 'sales'],
            ['code' => '5000', 'name' => 'HPP', 'type' => 'expense', 'subtype' => 'cost_of_goods_sold'],
            ['code' => '6000', 'name' => 'Beban Operasional', 'type' => 'expense', 'subtype' => 'operating_expense'],
        ] as $account) {
            AccountingAccount::create($account + ['is_active' => true]);
        }

        app(OpeningBalanceService::class)->post($owner, $owner->outlet, '2026-01-01', [
            'cash_balance' => '100000.00', 'bank_balance' => '50000.00', 'inventory_balance' => '0.00',
            'fixed_asset_balance' => '0.00', 'liabilities_balance' => '0.00', 'capital_balance' => '150000.00',
        ]);
    }

    private function account(string $subtype): int
    {
        return (int) AccountingAccount::where('subtype', $subtype)->value('id');
    }

    private function journal(User $owner, string $type, array $lines, string $date = '2026-01-05'): void
    {
        app(AccountingLedgerService::class)->createJournal($owner->outlet, $date, $type, $type, $lines, $owner);
    }

    public function test_cash_flow_uses_ledger_and_excludes_credit_purchase_from_cash_out(): void
    {
        $owner = $this->owner();
        $this->activate($owner);
        $this->journal($owner, 'sale', [
            ['account_id' => $this->account('cash'), 'debit' => '200.00'], ['account_id' => $this->account('sales'), 'credit' => '200.00'],
        ]);
        $this->journal($owner, 'sale', [
            ['account_id' => $this->account('bank'), 'debit' => '300.00'], ['account_id' => $this->account('sales'), 'credit' => '300.00'],
        ]);
        $this->journal($owner, 'stock_purchase', [
            ['account_id' => $this->account('inventory'), 'debit' => '100.00'], ['account_id' => $this->account('cash'), 'credit' => '100.00'],
        ]);
        $this->journal($owner, 'stock_purchase', [
            ['account_id' => $this->account('inventory'), 'debit' => '400.00'], ['account_id' => $this->account('payable'), 'credit' => '400.00'],
        ]);
        $this->journal($owner, 'payable_payment', [
            ['account_id' => $this->account('payable'), 'debit' => '50.00'], ['account_id' => $this->account('cash'), 'credit' => '50.00'],
        ]);
        $this->journal($owner, 'operating_expense', [
            ['account_id' => $this->account('operating_expense'), 'debit' => '30.00'], ['account_id' => $this->account('bank'), 'credit' => '30.00'],
        ]);
        $this->journal($owner, 'asset_purchase', [
            ['account_id' => $this->account('fixed_asset'), 'debit' => '70.00'], ['account_id' => $this->account('cash'), 'credit' => '70.00'],
        ]);

        $flow = app(CashFlowService::class)->build($owner->outlet_id, '2026-01-02', '2026-01-31');

        $this->assertSame('accounting_ledger', $flow['source']);
        $this->assertSame('500.00', $flow['cash_in_total']);
        $this->assertSame('100.00', $flow['cash_out_stock_purchases']);
        $this->assertSame('50.00', $flow['cash_out_payable_payments']);
        $this->assertSame('30.00', $flow['cash_out_expenses']);
        $this->assertSame('70.00', $flow['cash_out_assets']);
        $this->assertSame('250.00', $flow['net_change']);
        $this->assertSame('150000.00', $flow['opening_total']);
        $this->assertSame('150250.00', $flow['ending_total']);
        $this->assertTrue($flow['reconciled']);
    }

    public function test_before_cutover_is_limited_and_does_not_claim_ledger_finality(): void
    {
        $owner = $this->owner();
        $flow = app(CashFlowService::class)->build($owner->outlet_id, '2026-01-01', '2026-01-31', ['cash_in_cash' => 10]);

        $this->assertFalse($flow['accounting_active']);
        $this->assertSame('historical_operational', $flow['source']);
        $this->assertFalse($flow['reconciled']);
        $this->assertNull($flow['opening_total']);
    }

    public function test_internal_transfer_is_not_reported_as_net_cash_flow(): void
    {
        $owner = $this->owner();
        $this->activate($owner);
        $this->journal($owner, 'internal_transfer', [
            ['account_id' => $this->account('bank'), 'debit' => '100.00'], ['account_id' => $this->account('cash'), 'credit' => '100.00'],
        ]);

        $flow = app(CashFlowService::class)->build($owner->outlet_id, '2026-01-02', '2026-01-31');

        $this->assertSame('0.00', $flow['net_change']);
        $this->assertCount(1, $flow['internal_transfers']);
        $this->assertTrue($flow['reconciled']);
    }

    public function test_unclassified_cash_journal_is_flagged_and_not_silently_included(): void
    {
        $owner = $this->owner();
        $this->activate($owner);
        $this->journal($owner, 'legacy_cash_event', [
            ['account_id' => $this->account('cash'), 'debit' => '10.00'], ['account_id' => $this->account('sales'), 'credit' => '10.00'],
        ]);

        $flow = app(CashFlowService::class)->build($owner->outlet_id, '2026-01-02', '2026-01-31');

        $this->assertCount(1, $flow['unclassified']);
        $this->assertFalse($flow['reconciled']);
        $this->assertSame('10.00', $flow['difference']);
        $this->assertSame('0.00', $flow['operating_inflow']);
    }

    public function test_cash_flow_period_excludes_journals_outside_selected_dates(): void
    {
        $owner = $this->owner();
        $this->activate($owner);
        $this->journal($owner, 'sale', [['account_id' => $this->account('cash'), 'debit' => '100.00'], ['account_id' => $this->account('sales'), 'credit' => '100.00']], '2026-01-05');
        $this->journal($owner, 'sale', [['account_id' => $this->account('cash'), 'debit' => '900.00'], ['account_id' => $this->account('sales'), 'credit' => '900.00']], '2026-02-01');

        $flow = app(CashFlowService::class)->build($owner->outlet_id, '2026-01-01', '2026-01-31');

        $this->assertSame('100.00', $flow['cash_in_total']);
        $this->assertSame('150100.00', $flow['ending_total']);
    }

    public function test_mixed_cash_and_bank_journal_is_not_silently_classified(): void
    {
        $owner = $this->owner();
        $this->activate($owner);
        $this->journal($owner, 'legacy_mixed_cash', [
            ['account_id' => $this->account('cash'), 'debit' => '100.00'],
            ['account_id' => $this->account('bank'), 'credit' => '40.00'],
            ['account_id' => $this->account('sales'), 'credit' => '60.00'],
        ]);

        $flow = app(CashFlowService::class)->build($owner->outlet_id, '2026-01-02', '2026-01-31');

        $this->assertCount(1, $flow['unclassified']);
        $this->assertSame('0.00', $flow['operating_inflow']);
        $this->assertFalse($flow['reconciled']);
    }

    public function test_cash_flow_isolated_by_outlet(): void
    {
        $first = $this->owner();
        $this->activate($first);
        $second = $this->owner(Outlet::create(['name' => 'Outlet Kedua']));
        $this->journal($first, 'sale', [['account_id' => $this->account('cash'), 'debit' => '100.00'], ['account_id' => $this->account('sales'), 'credit' => '100.00']]);
        $flow = app(CashFlowService::class)->build($first->outlet_id, '2026-01-02', '2026-01-31');

        $this->assertSame('100.00', $flow['cash_in_total']);
        $this->assertDatabaseMissing('journal_entries', ['outlet_id' => $second->outlet_id, 'journal_type' => 'sale']);
    }
}
