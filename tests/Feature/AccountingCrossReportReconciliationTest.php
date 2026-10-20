<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\Outlet;
use App\Models\User;
use App\Services\AccountingLedgerService;
use App\Services\BalanceSheetService;
use App\Services\CashFlowService;
use App\Services\OpeningBalanceService;
use App\Services\ProfitLossService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountingCrossReportReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $outlet = Outlet::firstOrCreate(['name' => Outlet::DEFAULT_NAME]);

        return User::factory()->create([
            'name' => 'Owner Rekonsiliasi',
            'username' => 'owner-cross-'.Str::random(8),
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

    public function test_same_post_cutover_activity_reconciles_across_reports(): void
    {
        $owner = $this->owner();
        $this->activate($owner);
        app(AccountingLedgerService::class)->createJournal($owner->outlet, '2026-01-05', 'sale', 'Penjualan tunai', [
            ['account_id' => $this->account('cash'), 'debit' => '200.00'],
            ['account_id' => $this->account('sales'), 'credit' => '200.00'],
            ['account_id' => $this->account('cost_of_goods_sold'), 'debit' => '80.00'],
            ['account_id' => $this->account('inventory'), 'credit' => '80.00'],
        ], $owner);
        app(AccountingLedgerService::class)->createJournal($owner->outlet, '2026-01-05', 'operating_expense', 'Beban operasional', [
            ['account_id' => $this->account('operating_expense'), 'debit' => '50.00'],
            ['account_id' => $this->account('cash'), 'credit' => '50.00'],
        ], $owner);

        $balance = app(BalanceSheetService::class)->build($owner->outlet_id, '2026-01-31');
        $profitLoss = app(ProfitLossService::class)->build($owner->outlet_id, '2026-01-01', '2026-01-31');
        $cashFlow = app(CashFlowService::class)->build($owner->outlet_id, '2026-01-02', '2026-01-31');

        $this->assertTrue($balance['balanced']);
        $this->assertSame('150070.00', $balance['assets']['total_assets']);
        $this->assertSame('150070.00', $balance['total_liabilities_equity']);
        $this->assertSame('200.00', $profitLoss['net_sales']);
        $this->assertSame('80.00', $profitLoss['hpp']);
        $this->assertSame('50.00', $profitLoss['operating_expenses']);
        $this->assertSame('70.00', $profitLoss['net_profit']);
        $this->assertSame('150.00', $cashFlow['net_change']);
        $this->assertSame('150150.00', $cashFlow['ending_total']);
        $this->assertTrue($cashFlow['reconciled']);
    }
}
