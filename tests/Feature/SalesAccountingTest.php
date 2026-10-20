<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\Category;
use App\Models\JournalEntry;
use App\Models\Outlet;
use App\Models\OutletProductStock;
use App\Models\Product;
use App\Models\StockHistory;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use App\Services\OpeningBalanceService;
use App\Services\SalesAccountingService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalesAccountingTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $outlet = Outlet::firstOrCreate(['name' => Outlet::DEFAULT_NAME]);

        return User::factory()->create([
            'name' => 'Owner Sales',
            'username' => 'owner-sales-'.Str::random(6),
            'email' => Str::random(8).'@example.test',
            'role' => 'owner',
            'outlet_id' => $outlet->id,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
    }

    private function product(?int $purchasePrice = 10000): Product
    {
        $suffix = Str::random(6);
        $category = Category::create(['name' => 'Buah '.$suffix, 'slug' => 'buah-'.$suffix, 'is_active' => true]);
        $unit = Unit::create(['name' => 'Kilogram '.$suffix, 'symbol' => 'kg', 'is_active' => true]);

        return Product::create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'code' => 'SALE-'.$suffix,
            'name' => 'Produk Penjualan '.$suffix,
            'purchase_price' => $purchasePrice,
            'selling_price' => 20000,
            'is_active' => true,
        ]);
    }

    private function accounts(bool $includeSales = true): void
    {
        $accounts = [
            ['code' => '1000', 'name' => 'Kas Tunai', 'type' => 'asset', 'subtype' => 'cash'],
            ['code' => '1010', 'name' => 'Bank/QRIS', 'type' => 'asset', 'subtype' => 'bank'],
            ['code' => '1100', 'name' => 'Persediaan', 'type' => 'asset', 'subtype' => 'inventory'],
            ['code' => '3000', 'name' => 'Modal Pemilik', 'type' => 'equity', 'subtype' => 'capital'],
        ];
        if ($includeSales) {
            $accounts[] = ['code' => '4000', 'name' => 'Penjualan', 'type' => 'revenue', 'subtype' => 'sales'];
            $accounts[] = ['code' => '5000', 'name' => 'HPP', 'type' => 'expense', 'subtype' => 'cost_of_goods_sold'];
        }
        foreach ($accounts as $account) {
            AccountingAccount::create($account + ['is_active' => true]);
        }
    }

    private function activate(User $owner, bool $includeSales = true): void
    {
        $this->accounts($includeSales);
        app(OpeningBalanceService::class)->post($owner, $owner->outlet, '2026-01-01', [
            'cash_balance' => '100000.00',
            'bank_balance' => '0.00',
            'inventory_balance' => '0.00',
            'fixed_asset_balance' => '0.00',
            'liabilities_balance' => '0.00',
            'capital_balance' => '100000.00',
        ]);
    }

    private function stock(User $owner, Product $product, float $quantity = 5): OutletProductStock
    {
        return OutletProductStock::create([
            'outlet_id' => $owner->outlet_id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'minimum_stock' => 0,
        ]);
    }

    public function test_cash_sale_posts_balanced_sale_and_hpp_journal(): void
    {
        $owner = $this->owner();
        $product = $this->product(10000);
        $stock = $this->stock($owner, $product);
        $this->activate($owner);

        $transaction = app(TransactionService::class)->store($owner, [
            'payment_method' => 'cash',
            'cash_received' => 20000,
            'items' => [['product_id' => $product->id, 'weight_grams' => 1000]],
        ]);

        $journal = JournalEntry::query()->where('reference_type', Transaction::class)->where('reference_id', $transaction->id)->firstOrFail();
        $this->assertSame('sale', $journal->journal_type);
        $this->assertSame('30000', (string) $journal->lines()->sum('debit'));
        $this->assertSame('30000', (string) $journal->lines()->sum('credit'));
        $this->assertSame('4.000', $stock->refresh()->quantity);
        $this->assertDatabaseHas('stock_histories', ['reference_type' => Transaction::class, 'reference_id' => $transaction->id, 'type' => 'sale']);
    }

    public function test_qris_sale_uses_bank_account(): void
    {
        $owner = $this->owner();
        $product = $this->product();
        $this->stock($owner, $product);
        $this->activate($owner);

        $transaction = app(TransactionService::class)->store($owner, [
            'payment_method' => 'qris',
            'items' => [['product_id' => $product->id, 'weight_grams' => 500]],
        ]);

        $journal = JournalEntry::query()->where('reference_id', $transaction->id)->where('journal_type', 'sale')->firstOrFail();
        $bankLine = $journal->lines()->whereHas('account', fn ($query) => $query->where('subtype', 'bank'))->first();
        $this->assertNotNull($bankLine);
        $this->assertSame('10000.00', $bankLine->debit);
    }

    public function test_sale_without_purchase_price_posts_revenue_without_fake_hpp(): void
    {
        $owner = $this->owner();
        $product = $this->product(null);
        $this->stock($owner, $product);
        $this->activate($owner);

        $transaction = app(TransactionService::class)->store($owner, [
            'payment_method' => 'cash',
            'cash_received' => 20000,
            'items' => [['product_id' => $product->id, 'weight_grams' => 1000]],
        ]);

        $journal = JournalEntry::query()->where('reference_id', $transaction->id)->where('journal_type', 'sale')->firstOrFail();
        $this->assertCount(2, $journal->lines);
        $this->assertDatabaseMissing('journal_lines', ['journal_entry_id' => $journal->id, 'debit' => '10000.00']);
    }

    public function test_sale_before_cutover_does_not_create_journal(): void
    {
        $owner = $this->owner();
        $product = $this->product();
        $this->stock($owner, $product);

        $transaction = app(TransactionService::class)->store($owner, [
            'payment_method' => 'cash',
            'cash_received' => 20000,
            'items' => [['product_id' => $product->id, 'weight_grams' => 1000]],
        ]);

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_sale_rolls_back_when_required_sales_account_is_missing(): void
    {
        $owner = $this->owner();
        $product = $this->product();
        $stock = $this->stock($owner, $product);
        $this->activate($owner, false);

        try {
            app(TransactionService::class)->store($owner, [
                'payment_method' => 'cash',
                'cash_received' => 20000,
                'items' => [['product_id' => $product->id, 'weight_grams' => 1000]],
            ]);
            $this->fail('Transaction should fail when the sales account is missing.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseCount('transactions', 0);
        $this->assertSame('5.000', $stock->refresh()->quantity);
        $this->assertDatabaseCount('stock_histories', 0);
    }

    public function test_transaction_idempotency_prevents_duplicate_request_and_journal(): void
    {
        $owner = $this->owner();
        $product = $this->product();
        $this->stock($owner, $product);
        $this->activate($owner);
        $payload = [
            'payment_method' => 'cash',
            'cash_received' => 20000,
            'items' => [['product_id' => $product->id, 'weight_grams' => 1000]],
        ];

        app(TransactionService::class)->store($owner, $payload);
        try {
            app(TransactionService::class)->store($owner, $payload);
            $this->fail('Duplicate transaction should be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('journal_entries', 2);
    }

    public function test_distinct_idempotency_keys_allow_two_legitimate_identical_sales(): void
    {
        $owner = $this->owner();
        $product = $this->product(null);
        $stock = $this->stock($owner, $product, 3);
        $payload = [
            'payment_method' => 'cash',
            'cash_received' => 20000,
            'items' => [['product_id' => $product->id, 'weight_grams' => 1000]],
        ];

        app(TransactionService::class)->store($owner, $payload + ['idempotency_key' => (string) Str::uuid()]);
        app(TransactionService::class)->store($owner, $payload + ['idempotency_key' => (string) Str::uuid()]);

        $this->assertDatabaseCount('transactions', 2);
        $this->assertSame('1.000', $stock->refresh()->quantity);
    }

    public function test_repeating_sale_journal_is_rejected(): void
    {
        $owner = $this->owner();
        $product = $this->product();
        $this->stock($owner, $product);
        $this->activate($owner);
        $transaction = app(TransactionService::class)->store($owner, [
            'payment_method' => 'cash',
            'cash_received' => 20000,
            'items' => [['product_id' => $product->id, 'weight_grams' => 1000]],
        ]);

        $this->expectException(ValidationException::class);
        app(SalesAccountingService::class)->recordSale($transaction);
    }
}
