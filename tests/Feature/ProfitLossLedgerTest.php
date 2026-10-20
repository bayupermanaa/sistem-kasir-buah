<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\Category;
use App\Models\JournalEntry;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Unit;
use App\Models\User;
use App\Services\AccountingLedgerService;
use App\Services\OpeningBalanceService;
use App\Services\ProfitLossService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProfitLossLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $outlet = Outlet::firstOrCreate(['name' => Outlet::DEFAULT_NAME]);

        return User::factory()->create([
            'name' => 'Owner Laba Rugi',
            'username' => 'owner-profit-'.Str::random(6),
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
            ['code' => '4000', 'name' => 'Penjualan', 'type' => 'income', 'subtype' => 'sales'],
            ['code' => '5000', 'name' => 'HPP', 'type' => 'expense', 'subtype' => 'cost_of_goods_sold'],
            ['code' => '6000', 'name' => 'Beban Operasional', 'type' => 'expense', 'subtype' => 'operating_expense'],
        ] as $account) {
            AccountingAccount::create($account + ['is_active' => true]);
        }

        app(OpeningBalanceService::class)->post($owner, $owner->outlet, '2026-01-01', [
            'cash_balance' => '100000.00',
            'bank_balance' => '0.00',
            'inventory_balance' => '0.00',
            'fixed_asset_balance' => '0.00',
            'liabilities_balance' => '0.00',
            'capital_balance' => '100000.00',
        ]);
    }

    private function journal(User $owner, string $type, array $lines, string $date): void
    {
        app(AccountingLedgerService::class)->createJournal(
            outlet: $owner->outlet,
            journalDate: $date,
            journalType: $type,
            description: $type,
            lines: $lines,
            createdBy: $owner,
        );
    }

    private function account(string $subtype): int
    {
        return (int) AccountingAccount::where('subtype', $subtype)->value('id');
    }

    public function test_ledger_profit_loss_uses_period_and_excludes_non_expense_sources(): void
    {
        $owner = $this->owner();
        $this->activate($owner);
        $this->journal($owner, 'sale', [
            ['account_id' => $this->account('cash'), 'debit' => '200.00', 'credit' => '0.00'],
            ['account_id' => $this->account('sales'), 'debit' => '0.00', 'credit' => '200.00'],
        ], '2026-01-05');
        $this->journal($owner, 'hpp', [
            ['account_id' => $this->account('cost_of_goods_sold'), 'debit' => '80.00', 'credit' => '0.00'],
            ['account_id' => $this->account('inventory'), 'debit' => '0.00', 'credit' => '80.00'],
        ], '2026-01-05');
        $this->journal($owner, 'operating_expense', [
            ['account_id' => $this->account('operating_expense'), 'debit' => '50.00', 'credit' => '0.00'],
            ['account_id' => $this->account('cash'), 'debit' => '0.00', 'credit' => '50.00'],
        ], '2026-01-05');
        $this->journal($owner, 'stock_purchase', [
            ['account_id' => $this->account('inventory'), 'debit' => '500.00', 'credit' => '0.00'],
            ['account_id' => $this->account('cash'), 'debit' => '0.00', 'credit' => '500.00'],
        ], '2026-01-05');
        $this->journal($owner, 'asset_purchase', [
            ['account_id' => $this->account('fixed_asset'), 'debit' => '100.00', 'credit' => '0.00'],
            ['account_id' => $this->account('cash'), 'debit' => '0.00', 'credit' => '100.00'],
        ], '2026-01-05');
        $this->journal($owner, 'sale_outside_period', [
            ['account_id' => $this->account('cash'), 'debit' => '999.00', 'credit' => '0.00'],
            ['account_id' => $this->account('sales'), 'debit' => '0.00', 'credit' => '999.00'],
        ], '2026-02-01');

        $profitLoss = app(ProfitLossService::class)->build($owner->outlet_id, '2026-01-01', '2026-01-31');

        $this->assertSame('200.00', $profitLoss['net_sales']);
        $this->assertSame('80.00', $profitLoss['hpp']);
        $this->assertSame('50.00', $profitLoss['operating_expenses']);
        $this->assertSame('120.00', $profitLoss['gross_profit']);
        $this->assertSame('70.00', $profitLoss['net_profit']);
        $this->assertTrue($profitLoss['accounting_complete']);
    }

    public function test_before_cutover_uses_historical_fallback_with_limitation(): void
    {
        $owner = $this->owner();

        $profitLoss = app(ProfitLossService::class)->build($owner->outlet_id, '2025-01-01', '2025-01-31', [
            'net_sales' => '100.00',
            'hpp' => null,
            'gross_profit' => null,
            'operating_expenses' => '20.00',
            'net_profit' => null,
            'hpp_complete' => false,
            'missing_hpp_products' => collect(['Produk historis']),
        ]);

        $this->assertFalse($profitLoss['accounting_active']);
        $this->assertSame('historical_operational', $profitLoss['source']);
        $this->assertNull($profitLoss['net_profit']);
    }

    public function test_missing_hpp_does_not_become_zero_or_fake_profit(): void
    {
        $owner = $this->owner();
        $this->activate($owner);
        $category = Category::create(['name' => 'Kategori HPP', 'slug' => 'kategori-hpp', 'is_active' => true]);
        $unit = Unit::create(['name' => 'Unit HPP', 'symbol' => 'pcs', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'code' => 'HPP-LEDGER-001',
            'name' => 'Produk HPP Tidak Diketahui',
            'purchase_price' => null,
            'selling_price' => '100.00',
            'is_active' => true,
        ]);
        $transaction = Transaction::create([
            'invoice_number' => 'TRX-LEDGER-HPP-001',
            'outlet_id' => $owner->outlet_id,
            'user_id' => $owner->id,
            'subtotal' => '100.00',
            'discount' => '0.00',
            'total' => '100.00',
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);
        $transaction->created_at = Carbon::parse('2026-01-05');
        $transaction->updated_at = Carbon::parse('2026-01-05');
        $transaction->save();
        TransactionItem::create([
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'quantity' => '1.000',
            'weight_grams' => 1000,
            'price_per_unit' => '100.00',
            'subtotal' => '100.00',
        ]);
        $this->journal($owner, 'sale', [
            ['account_id' => $this->account('cash'), 'debit' => '100.00', 'credit' => '0.00'],
            ['account_id' => $this->account('sales'), 'debit' => '0.00', 'credit' => '100.00'],
        ], '2026-01-05');

        $profitLoss = app(ProfitLossService::class)->build($owner->outlet_id, '2026-01-01', '2026-01-31');

        $this->assertSame('100.00', $profitLoss['net_sales']);
        $this->assertNull($profitLoss['hpp']);
        $this->assertNull($profitLoss['gross_profit']);
        $this->assertNull($profitLoss['net_profit']);
        $this->assertFalse($profitLoss['hpp_complete']);
        $this->assertContains('Produk HPP Tidak Diketahui', $profitLoss['missing_hpp_products']);
    }
}
