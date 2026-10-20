<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\Category;
use App\Models\Outlet;
use App\Models\OutletProductStock;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierPayable;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use App\Services\BalanceSheetService;
use App\Services\CashFlowService;
use App\Services\OpeningBalanceService;
use App\Services\ProfitLossService;
use App\Services\StockService;
use App\Services\SupplierPayableService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class EndToEndBusinessFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_cutover_business_flow_reconciles_stock_payables_and_reports(): void
    {
        $outlet = Outlet::firstOrCreate(['name' => Outlet::DEFAULT_NAME]);
        $owner = User::factory()->create([
            'name' => 'Owner E2E',
            'username' => 'owner-e2e-'.Str::random(8),
            'email' => Str::random(8).'@example.test',
            'role' => 'owner',
            'outlet_id' => $outlet->id,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $this->createAccounts();
        app(OpeningBalanceService::class)->post($owner, $outlet, '2026-10-01', [
            'cash_balance' => '100000.00',
            'bank_balance' => '50000.00',
            'inventory_balance' => '0.00',
            'fixed_asset_balance' => '0.00',
            'liabilities_balance' => '0.00',
            'capital_balance' => '150000.00',
        ]);

        $product = $this->createProduct();
        OutletProductStock::create([
            'outlet_id' => $outlet->id,
            'product_id' => $product->id,
            'quantity' => '10.000',
            'minimum_stock' => '1.000',
        ]);

        app(TransactionService::class)->store($owner, [
            'payment_method' => 'qris',
            'items' => [['product_id' => $product->id, 'quantity' => '1.000']],
        ]);

        $stockService = app(StockService::class);
        $stockService->purchaseStock($owner, $outlet, $product, '2.000', '50.00', '2026-10-20', 'cash');

        $supplier = Supplier::create(['name' => 'Supplier E2E', 'is_active' => true]);
        $stockService->purchaseStock($owner, $outlet, $product, '1.000', '40.00', '2026-10-20', 'credit', null, $supplier->id, 'INV-E2E-001', '2026-10-31');
        $payable = SupplierPayable::firstOrFail();
        app(SupplierPayableService::class)->pay($owner, $payable, [
            'amount' => '20.00',
            'payment_date' => '2026-10-20',
            'payment_method' => 'cash',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $this->actingAs($owner)->post(route('expenses.store'), [
            'expense_date' => '2026-10-20',
            'category' => 'Operasional',
            'description' => 'Biaya operasional E2E',
            'amount' => '30.00',
            'payment_method' => 'cash',
        ])->assertRedirect(route('expenses.index'));

        $this->actingAs($owner)->post(route('assets.store'), [
            'name' => 'Rak E2E',
            'category' => 'Furniture',
            'purchase_date' => '2026-10-20',
            'purchase_price' => '500.00',
            'quantity' => 1,
            'payment_method' => 'transfer',
            'status' => 'Aktif',
        ])->assertRedirect(route('assets.index'));

        $balance = app(BalanceSheetService::class)->build($outlet->id, '2026-12-31');
        $profitLoss = app(ProfitLossService::class)->build($outlet->id, '2026-10-01', '2026-12-31');
        $cashFlow = app(CashFlowService::class)->build($outlet->id, '2026-10-01', '2026-12-31');

        $this->assertSame('99850.00', $balance['assets']['cash']);
        $this->assertSame('49700.00', $balance['assets']['bank']);
        $this->assertSame('40.00', $balance['assets']['inventory_value']);
        $this->assertSame('500.00', $balance['assets']['fixed_asset_value']);
        $this->assertSame('20.00', $balance['liabilities']['total']);
        $this->assertSame('150090.00', $balance['assets']['total_assets']);
        $this->assertSame('150090.00', $balance['total_liabilities_equity']);
        $this->assertTrue($balance['balanced']);

        $this->assertSame('200.00', $profitLoss['net_sales']);
        $this->assertSame('100.00', $profitLoss['hpp']);
        $this->assertSame('30.00', $profitLoss['operating_expenses']);
        $this->assertSame('70.00', $profitLoss['net_profit']);

        $this->assertSame('200.00', $cashFlow['cash_in_qris']);
        $this->assertSame('100.00', $cashFlow['cash_out_stock_purchases']);
        $this->assertSame('20.00', $cashFlow['cash_out_payable_payments']);
        $this->assertSame('30.00', $cashFlow['cash_out_expenses']);
        $this->assertSame('500.00', $cashFlow['cash_out_assets']);
        $this->assertSame('-450.00', $cashFlow['net_change']);
        $this->assertTrue($cashFlow['reconciled']);

        $this->assertSame('12.000', OutletProductStock::firstOrFail()->quantity);
        $this->assertSame(1, Transaction::count());
        $this->assertSame(7, \App\Models\JournalEntry::count());
        $this->assertDatabaseCount('expenses', 1);
        $this->assertDatabaseCount('assets', 1);
    }

    private function createAccounts(): void
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
    }

    private function createProduct(): Product
    {
        $suffix = Str::random(8);
        $category = Category::create(['name' => 'Buah E2E '.$suffix, 'slug' => 'buah-e2e-'.$suffix, 'is_active' => true]);
        $unit = Unit::create(['name' => 'Kilogram E2E '.$suffix, 'symbol' => 'kg'.$suffix, 'is_active' => true]);

        return Product::create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'code' => 'E2E-'.$suffix,
            'name' => 'Produk E2E',
            'purchase_price' => '100.00',
            'selling_price' => '200.00',
            'is_active' => true,
        ]);
    }
}
