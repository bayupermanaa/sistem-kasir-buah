<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\Asset;
use App\Models\Category;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Unit;
use App\Models\User;
use App\Services\AccountingLedgerService;
use App\Services\BalanceSheetService;
use App\Services\OpeningBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class BalanceSheetLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $outlet = Outlet::firstOrCreate(['name' => Outlet::DEFAULT_NAME]);

        return User::factory()->create([
            'name' => 'Owner Neraca',
            'username' => 'owner-balance-'.Str::random(6),
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
            'bank_balance' => '50000.00',
            'inventory_balance' => '500000.00',
            'fixed_asset_balance' => '200000.00',
            'liabilities_balance' => '300000.00',
            'capital_balance' => '550000.00',
        ]);
    }

    private function journal(User $owner, string $type, array $lines, string $date = '2026-01-02'): void
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

    public function test_balance_sheet_is_unavailable_before_cutover(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->get(route('reports.balance-sheet'))
            ->assertOk()
            ->assertSee('Neraca lengkap belum tersedia')
            ->assertSee('Belum tersedia');
    }

    public function test_balance_sheet_uses_ledger_balances_and_is_balanced(): void
    {
        $owner = $this->owner();
        $this->activate($owner);
        $this->journal($owner, 'sale', [
            ['account_id' => AccountingAccount::where('subtype', 'cash')->value('id'), 'debit' => '200.00', 'credit' => '0.00'],
            ['account_id' => AccountingAccount::where('subtype', 'sales')->value('id'), 'debit' => '0.00', 'credit' => '200.00'],
        ]);
        $this->journal($owner, 'operating_expense', [
            ['account_id' => AccountingAccount::where('subtype', 'operating_expense')->value('id'), 'debit' => '50.00', 'credit' => '0.00'],
            ['account_id' => AccountingAccount::where('subtype', 'cash')->value('id'), 'debit' => '0.00', 'credit' => '50.00'],
        ]);
        $this->journal($owner, 'stock_purchase', [
            ['account_id' => AccountingAccount::where('subtype', 'inventory')->value('id'), 'debit' => '400.00', 'credit' => '0.00'],
            ['account_id' => AccountingAccount::where('subtype', 'payable')->value('id'), 'debit' => '0.00', 'credit' => '400.00'],
        ]);
        $this->journal($owner, 'asset_purchase', [
            ['account_id' => AccountingAccount::where('subtype', 'fixed_asset')->value('id'), 'debit' => '100.00', 'credit' => '0.00'],
            ['account_id' => AccountingAccount::where('subtype', 'cash')->value('id'), 'debit' => '0.00', 'credit' => '100.00'],
        ]);
        $this->journal($owner, 'payable_payment', [
            ['account_id' => AccountingAccount::where('subtype', 'payable')->value('id'), 'debit' => '100.00', 'credit' => '0.00'],
            ['account_id' => AccountingAccount::where('subtype', 'cash')->value('id'), 'debit' => '0.00', 'credit' => '100.00'],
        ]);

        $balance = app(BalanceSheetService::class)->build($owner->outlet_id, '2026-01-31');

        $this->assertTrue($balance['accounting_complete']);
        $this->assertTrue($balance['balanced']);
        $this->assertSame('850450.00', $balance['assets']['total_assets']);
        $this->assertSame('300300.00', $balance['liabilities']['total']);
        $this->assertSame('550150.00', $balance['equity']['total']);
        $this->assertSame('850450.00', $balance['total_liabilities_equity']);
        $this->assertSame('0.00', $balance['difference']);
    }

    public function test_balance_sheet_detects_unbalanced_ledger_without_adjustment(): void
    {
        $owner = $this->owner();
        $this->activate($owner);
        $cash = AccountingAccount::where('subtype', 'cash')->firstOrFail();
        $entry = JournalEntry::create([
            'outlet_id' => $owner->outlet_id,
            'journal_date' => '2026-01-02',
            'journal_type' => 'legacy_invalid',
            'description' => 'Simulasi ledger tidak seimbang',
            'created_by' => $owner->id,
        ]);
        JournalLine::create([
            'journal_entry_id' => $entry->id,
            'accounting_account_id' => $cash->id,
            'debit' => '10.00',
            'credit' => '0.00',
        ]);

        $balance = app(BalanceSheetService::class)->build($owner->outlet_id, '2026-01-31');

        $this->assertFalse($balance['balanced']);
        $this->assertSame('10.00', $balance['difference']);
        $this->assertContains('Saldo akun belum seimbang.', $balance['missing']);
    }

    public function test_existing_pre_cutover_asset_is_not_added_again_to_ledger(): void
    {
        $owner = $this->owner();
        $this->activate($owner);
        Asset::create([
            'outlet_id' => $owner->outlet_id,
            'asset_code' => 'AST-HIST-001',
            'name' => 'Aset Lama',
            'category' => 'Elektronik',
            'purchase_date' => '2025-12-31',
            'purchase_price' => '200000.00',
            'quantity' => 1,
            'total_cost' => '200000.00',
            'payment_method' => 'cash',
            'status' => 'Aktif',
        ]);

        $balance = app(BalanceSheetService::class)->build($owner->outlet_id, '2026-01-31');

        $this->assertSame('200000.00', $balance['assets']['fixed_asset_value']);
        $this->assertSame(1, JournalEntry::query()->where('journal_type', 'opening_balance')->count());
        $this->assertDatabaseMissing('journal_entries', ['journal_type' => 'asset_purchase']);
    }

    public function test_missing_purchase_price_marks_hpp_incomplete_without_fake_profit(): void
    {
        $owner = $this->owner();
        $this->activate($owner);
        $category = Category::create(['name' => 'Buah HPP Test', 'slug' => 'buah-hpp-test', 'is_active' => true]);
        $unit = Unit::create(['name' => 'Kilogram HPP Test', 'symbol' => 'kg', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'code' => 'HPP-TEST-001',
            'name' => 'Produk Tanpa Harga Beli',
            'purchase_price' => null,
            'selling_price' => '10000.00',
            'is_active' => true,
        ]);
        $transaction = Transaction::create([
            'invoice_number' => 'TRX-HPP-TEST-001',
            'outlet_id' => $owner->outlet_id,
            'user_id' => $owner->id,
            'subtotal' => '10000.00',
            'discount' => '0.00',
            'total' => '10000.00',
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);
        $transaction->created_at = Carbon::parse('2026-01-02');
        $transaction->updated_at = Carbon::parse('2026-01-02');
        $transaction->save();
        TransactionItem::create([
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'quantity' => '1.000',
            'weight_grams' => 1000,
            'price_per_unit' => '10000.00',
            'subtotal' => '10000.00',
        ]);

        $balance = app(BalanceSheetService::class)->build($owner->outlet_id, '2026-01-31');

        $this->assertFalse($balance['hpp_complete']);
        $this->assertNull($balance['equity']['current_profit']);
        $this->assertFalse($balance['balanced']);
        $this->assertContains('Produk Tanpa Harga Beli', $balance['missing_hpp_products']);
    }

    public function test_balance_sheet_excludes_pre_cutover_journal(): void
    {
        $owner = $this->owner();
        $this->activate($owner);
        $cash = AccountingAccount::where('subtype', 'cash')->firstOrFail();
        $sales = AccountingAccount::where('subtype', 'sales')->firstOrFail();
        $entry = JournalEntry::create([
            'outlet_id' => $owner->outlet_id,
            'journal_date' => '2025-12-31',
            'journal_type' => 'legacy_sale',
            'description' => 'Jurnal historis di luar cutover',
            'created_by' => $owner->id,
        ]);
        JournalLine::create(['journal_entry_id' => $entry->id, 'accounting_account_id' => $cash->id, 'debit' => '100.00', 'credit' => '0.00']);
        JournalLine::create(['journal_entry_id' => $entry->id, 'accounting_account_id' => $sales->id, 'debit' => '0.00', 'credit' => '100.00']);

        $balance = app(BalanceSheetService::class)->build($owner->outlet_id, '2026-01-31');

        $this->assertSame('100000.00', $balance['assets']['cash']);
        $this->assertSame('850000.00', $balance['assets']['total_assets']);
        $this->assertTrue($balance['balanced']);
    }
}
