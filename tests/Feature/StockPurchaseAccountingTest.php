<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\Category;
use App\Models\Outlet;
use App\Models\OutletProductStock;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierPayable;
use App\Models\Unit;
use App\Models\User;
use App\Services\OpeningBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockPurchaseAccountingTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $outlet = Outlet::firstOrCreate(['name' => Outlet::DEFAULT_NAME]);

        return User::factory()->create([
            'name' => 'Owner Purchase',
            'username' => 'owner-purchase-'.Str::random(6),
            'email' => Str::random(8).'@example.test',
            'role' => 'owner',
            'outlet_id' => $outlet->id,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
    }

    private function cashier(): User
    {
        return User::factory()->create([
            'role' => 'cashier',
            'outlet_id' => $this->owner()->outlet_id,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
    }

    private function product(): Product
    {
        $suffix = Str::random(6);
        $category = Category::create(['name' => 'Kategori '.$suffix, 'slug' => 'kategori-'.$suffix, 'is_active' => true]);
        $unit = Unit::create(['name' => 'Kilogram '.$suffix, 'symbol' => 'kg', 'is_active' => true]);

        return Product::create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'code' => 'PUR-'.$suffix,
            'name' => 'Produk Pembelian '.$suffix,
            'purchase_price' => null,
            'selling_price' => 20000,
            'is_active' => true,
        ]);
    }

    private function accounts(bool $cash = true): void
    {
        $accounts = [
            ['code' => '1100', 'name' => 'Persediaan', 'type' => 'asset', 'subtype' => 'inventory'],
            ['code' => '2000', 'name' => 'Utang Supplier', 'type' => 'liability', 'subtype' => 'payable'],
            ['code' => '3000', 'name' => 'Modal Pemilik', 'type' => 'equity', 'subtype' => 'capital'],
        ];
        if ($cash) {
            $accounts[] = ['code' => '1000', 'name' => 'Kas Tunai', 'type' => 'asset', 'subtype' => 'cash'];
        }
        foreach ($accounts as $account) {
            AccountingAccount::create($account + ['is_active' => true]);
        }
    }

    private function activateAccounting(User $owner, bool $cash = true): void
    {
        $this->accounts($cash);
        app(OpeningBalanceService::class)->post($owner, $owner->outlet, '2026-12-01', [
            'cash_balance' => $cash ? '100000.00' : '0.00',
            'bank_balance' => '0.00',
            'inventory_balance' => $cash ? '0.00' : '100.00',
            'fixed_asset_balance' => '0.00',
            'liabilities_balance' => '0.00',
            'capital_balance' => $cash ? '100000.00' : '100.00',
        ]);
    }

    public function test_active_cash_purchase_adds_stock_and_balanced_journal(): void
    {
        $owner = $this->owner();
        $product = $this->product();
        $stock = OutletProductStock::create(['outlet_id' => $owner->outlet_id, 'product_id' => $product->id, 'quantity' => 1, 'minimum_stock' => 0]);
        $this->activateAccounting($owner);

        $this->actingAs($owner)->post(route('stocks.purchase.store', $product), [
            'quantity' => '2.500', 'unit_cost' => '10000.00', 'purchase_date' => '2026-12-02', 'payment_method' => 'cash',
        ])->assertRedirect(route('products.index'));

        $this->assertSame('3.500', $stock->refresh()->quantity);
        $purchase = \App\Models\StockPurchase::firstOrFail();
        $this->assertDatabaseHas('journal_entries', ['reference_type' => \App\Models\StockPurchase::class, 'reference_id' => $purchase->id, 'journal_type' => 'stock_purchase']);
        $this->assertSame('25000.00', (string) $purchase->total_cost);
        $this->assertDatabaseCount('journal_entries', 2);
        $this->assertNull($purchase->payable);
    }

    public function test_active_credit_purchase_creates_payable_without_cash_expense(): void
    {
        $owner = $this->owner();
        $product = $this->product();
        OutletProductStock::create(['outlet_id' => $owner->outlet_id, 'product_id' => $product->id, 'quantity' => 0, 'minimum_stock' => 0]);
        $supplier = Supplier::create(['name' => 'Supplier Kredit', 'is_active' => true]);
        $this->activateAccounting($owner);

        $this->actingAs($owner)->post(route('stocks.purchase.store', $product), [
            'quantity' => '3.000', 'unit_cost' => '12000.00', 'purchase_date' => '2026-12-02', 'payment_method' => 'credit',
            'supplier_id' => $supplier->id, 'invoice_number' => 'INV-KREDIT-001', 'due_date' => '2026-12-31',
        ])->assertRedirect(route('products.index'));

        $purchase = \App\Models\StockPurchase::firstOrFail();
        $payable = SupplierPayable::firstOrFail();
        $this->assertSame($supplier->id, $purchase->supplier_id);
        $this->assertSame($purchase->id, $payable->source_id);
        $this->assertSame('open', $payable->status);
        $this->assertSame('36000.00', $payable->outstanding_amount);
        $this->assertDatabaseMissing('expenses', ['description' => 'Utang pembelian stok']);
    }

    public function test_credit_purchase_is_rejected_before_cutover_without_changes(): void
    {
        $owner = $this->owner();
        $product = $this->product();
        $supplier = Supplier::create(['name' => 'Supplier Pending', 'is_active' => true]);
        OutletProductStock::create(['outlet_id' => $owner->outlet_id, 'product_id' => $product->id, 'quantity' => 1, 'minimum_stock' => 0]);

        $this->actingAs($owner)->post(route('stocks.purchase.store', $product), [
            'quantity' => '2.000', 'unit_cost' => '10000.00', 'purchase_date' => '2026-12-02', 'payment_method' => 'credit',
            'supplier_id' => $supplier->id, 'due_date' => '2026-12-31',
        ])->assertSessionHasErrors('accounting');

        $this->assertDatabaseCount('stock_purchases', 0);
        $this->assertDatabaseCount('supplier_payables', 0);
        $this->assertSame('1.000', OutletProductStock::first()->quantity);
    }

    public function test_cashier_cannot_record_stock_purchase(): void
    {
        $owner = $this->owner();
        $product = $this->product();

        $this->actingAs($this->cashier())->post(route('stocks.purchase.store', $product), [
            'quantity' => '1.000', 'unit_cost' => '10000.00', 'purchase_date' => '2026-12-02', 'payment_method' => 'cash',
        ])->assertForbidden();
    }

    public function test_purchase_rolls_back_when_cash_journal_cannot_be_created(): void
    {
        $owner = $this->owner();
        $product = $this->product();
        $stock = OutletProductStock::create(['outlet_id' => $owner->outlet_id, 'product_id' => $product->id, 'quantity' => 1, 'minimum_stock' => 0]);
        $this->activateAccounting($owner, false);

        $this->actingAs($owner)->post(route('stocks.purchase.store', $product), [
            'quantity' => '2.000', 'unit_cost' => '10000.00', 'purchase_date' => '2026-12-02', 'payment_method' => 'cash',
        ])->assertSessionHasErrors('accounting');

        $this->assertDatabaseCount('stock_purchases', 0);
        $this->assertSame('1.000', $stock->refresh()->quantity);
    }
}
