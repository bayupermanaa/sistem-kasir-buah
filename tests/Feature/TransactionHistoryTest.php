<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TransactionHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function defaultOutlet(): Outlet
    {
        return Outlet::firstOrCreate([
            'name' => 'Toko Buah Teras Fatimah',
        ], [
            'address' => null,
            'phone' => null,
        ]);
    }

    private function createOutlet(string $name): Outlet
    {
        return Outlet::create([
            'name' => $name,
            'address' => null,
            'phone' => null,
        ]);
    }

    private function createProduct(string $code, string $name, float $sellingPrice): Product
    {
        $suffix = str()->random(5);

        $category = Category::create([
            'name' => 'Kategori '.$suffix,
            'slug' => 'kategori-'.$suffix,
            'description' => null,
            'is_active' => true,
        ]);

        $unit = Unit::create([
            'name' => 'Satuan '.$suffix,
            'symbol' => 'st'.$suffix,
            'is_active' => true,
        ]);

        return Product::create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'code' => $code,
            'name' => $name,
            'description' => null,
            'image' => null,
            'purchase_price' => $sellingPrice - 1000,
            'selling_price' => $sellingPrice,
            'is_active' => true,
        ]);
    }

    private function ownerUser(): User
    {
        return User::factory()->create([
            'name' => 'Owner',
            'username' => 'owner-'.str()->random(5),
            'email' => 'owner-'.str()->random(5).'@terasfatimah.test',
            'role' => 'owner',
            'outlet_id' => null,
            'is_active' => true,
        ]);
    }

    private function cashierUser(Outlet $outlet, string $name = 'Cashier'): User
    {
        return User::factory()->create([
            'name' => $name,
            'username' => strtolower($name).'-'.str()->random(5),
            'email' => strtolower($name).'-'.str()->random(5).'@terasfatimah.test',
            'role' => 'cashier',
            'outlet_id' => $outlet->id,
            'is_active' => true,
        ]);
    }

    private function createTransactionRecord(array $attributes): Transaction
    {
        $transaction = Transaction::create(array_merge([
            'discount' => 0,
            'cash_received' => 0,
            'change_amount' => 0,
            'status' => 'completed',
        ], $attributes));

        if (! empty($attributes['created_at'])) {
            $timestamp = Carbon::parse($attributes['created_at']);
            $transaction->forceFill([
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->saveQuietly();
        }

        return $transaction;
    }

    private function attachItem(Transaction $transaction, Product $product, float $quantity, float $subtotal): void
    {
        TransactionItem::create([
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'weight_grams' => (int) round($quantity * 1000),
            'price_per_unit' => $subtotal / $quantity,
            'subtotal' => $subtotal,
        ]);
    }

    public function test_cashier_can_open_transaction_history_page(): void
    {
        $outlet = $this->defaultOutlet();
        $cashier = $this->cashierUser($outlet);
        $product = $this->createProduct('HIS-001', 'Mangga', 10000);

        $transaction = $this->createTransactionRecord([
            'invoice_number' => 'TRX-HIS-001',
            'outlet_id' => $outlet->id,
            'user_id' => $cashier->id,
            'subtotal' => 10000,
            'total' => 10000,
            'payment_method' => 'cash',
        ]);
        $this->attachItem($transaction, $product, 1, 10000);

        $this->actingAs($cashier)
            ->get('/riwayat-transaksi')
            ->assertOk()
            ->assertSee('Riwayat Transaksi')
            ->assertSee('TRX-HIS-001');
    }

    public function test_cashier_only_sees_own_outlet_transactions(): void
    {
        $cashierOutlet = $this->defaultOutlet();
        $otherOutlet = $this->createOutlet('Outlet Lain');
        $cashier = $this->cashierUser($cashierOutlet);
        $product = $this->createProduct('HIS-010', 'Apel', 12000);

        $ownTransaction = $this->createTransactionRecord([
            'invoice_number' => 'TRX-OWN-010',
            'outlet_id' => $cashierOutlet->id,
            'user_id' => $cashier->id,
            'subtotal' => 12000,
            'total' => 12000,
            'payment_method' => 'cash',
        ]);
        $this->attachItem($ownTransaction, $product, 1, 12000);

        $otherTransaction = $this->createTransactionRecord([
            'invoice_number' => 'TRX-OTH-010',
            'outlet_id' => $otherOutlet->id,
            'user_id' => $cashier->id,
            'subtotal' => 12000,
            'total' => 12000,
            'payment_method' => 'cash',
        ]);
        $this->attachItem($otherTransaction, $product, 1, 12000);

        $this->actingAs($cashier)
            ->get('/riwayat-transaksi')
            ->assertOk()
            ->assertSee('TRX-OWN-010')
            ->assertDontSee('TRX-OTH-010');
    }

    public function test_other_outlet_transaction_detail_is_rejected_for_cashier(): void
    {
        $cashierOutlet = $this->createOutlet('Outlet Kasir');
        $otherOutlet = $this->createOutlet('Outlet Lain');
        $cashier = $this->cashierUser($cashierOutlet);
        $product = $this->createProduct('HIS-020', 'Jeruk', 14000);

        $transaction = $this->createTransactionRecord([
            'invoice_number' => 'TRX-OTH-020',
            'outlet_id' => $otherOutlet->id,
            'user_id' => $cashier->id,
            'subtotal' => 14000,
            'total' => 14000,
            'payment_method' => 'cash',
        ]);
        $this->attachItem($transaction, $product, 1, 14000);

        $this->actingAs($cashier)
            ->get(route('kasir.show', $transaction))
            ->assertForbidden();
    }

    public function test_invoice_search_filter_works(): void
    {
        $outlet = $this->defaultOutlet();
        $cashier = $this->cashierUser($outlet);
        $product = $this->createProduct('HIS-030', 'Nanas', 9000);

        $first = $this->createTransactionRecord([
            'invoice_number' => 'TRX-SEARCH-001',
            'outlet_id' => $outlet->id,
            'user_id' => $cashier->id,
            'subtotal' => 9000,
            'total' => 9000,
            'payment_method' => 'cash',
        ]);
        $this->attachItem($first, $product, 1, 9000);

        $second = $this->createTransactionRecord([
            'invoice_number' => 'TRX-SEARCH-002',
            'outlet_id' => $outlet->id,
            'user_id' => $cashier->id,
            'subtotal' => 9000,
            'total' => 9000,
            'payment_method' => 'cash',
        ]);
        $this->attachItem($second, $product, 1, 9000);

        $this->actingAs($cashier)
            ->get('/riwayat-transaksi?search=001')
            ->assertOk()
            ->assertSee('TRX-SEARCH-001')
            ->assertDontSee('TRX-SEARCH-002');
    }

    public function test_date_payment_cashier_filters_work_and_summary_follows_filter(): void
    {
        $outlet = $this->defaultOutlet();
        $cashierA = $this->cashierUser($outlet, 'Cashier A');
        $cashierB = $this->cashierUser($outlet, 'Cashier B');
        $product = $this->createProduct('HIS-040', 'Semangka', 15000);

        $oldTransaction = $this->createTransactionRecord([
            'invoice_number' => 'TRX-DATE-001',
            'outlet_id' => $outlet->id,
            'user_id' => $cashierA->id,
            'subtotal' => 15000,
            'total' => 15000,
            'payment_method' => 'cash',
            'created_at' => '2026-07-20 10:00:00',
        ]);
        $this->attachItem($oldTransaction, $product, 1, 15000);

        $newCashTransaction = $this->createTransactionRecord([
            'invoice_number' => 'TRX-DATE-002',
            'outlet_id' => $outlet->id,
            'user_id' => $cashierA->id,
            'subtotal' => 20000,
            'total' => 20000,
            'payment_method' => 'cash',
            'created_at' => '2026-07-23 10:00:00',
        ]);
        $this->attachItem($newCashTransaction, $product, 1, 20000);

        $qrisTransaction = $this->createTransactionRecord([
            'invoice_number' => 'TRX-DATE-003',
            'outlet_id' => $outlet->id,
            'user_id' => $cashierB->id,
            'subtotal' => 30000,
            'total' => 30000,
            'payment_method' => 'qris',
            'created_at' => '2026-07-23 12:00:00',
        ]);
        $this->attachItem($qrisTransaction, $product, 2, 30000);

        $this->actingAs($cashierA)
            ->get('/riwayat-transaksi?date_from=2026-07-23&date_to=2026-07-24&payment_method=cash')
            ->assertOk()
            ->assertSee('TRX-DATE-002')
            ->assertDontSee('TRX-DATE-001')
            ->assertDontSee('TRX-DATE-003');

        $this->actingAs($cashierA)
            ->get('/riwayat-transaksi?date_from=2026-07-23&date_to=2026-07-24&payment_method=cash')
            ->assertSee('Jumlah Transaksi')
            ->assertSee('1')
            ->assertSee('Rp 20.000');

        $owner = $this->ownerUser();

        $this->actingAs($owner)
            ->get('/riwayat-transaksi?cashier_id='.$cashierB->id)
            ->assertOk()
            ->assertSee('TRX-DATE-003')
            ->assertDontSee('TRX-DATE-002');
    }

    public function test_pagination_keeps_query_string(): void
    {
        $outlet = $this->defaultOutlet();
        $cashier = $this->cashierUser($outlet);
        $product = $this->createProduct('HIS-050', 'Pisang', 8000);

        for ($index = 1; $index <= 11; $index++) {
            $transaction = $this->createTransactionRecord([
                'invoice_number' => 'TRX-PAGE-'.$index,
                'outlet_id' => $outlet->id,
                'user_id' => $cashier->id,
                'subtotal' => 8000,
                'total' => 8000,
                'payment_method' => 'cash',
            ]);
            $this->attachItem($transaction, $product, 1, 8000);
        }

        $this->actingAs($cashier)
            ->get('/riwayat-transaksi?search=TRX-PAGE')
            ->assertOk()
            ->assertSee('page=2')
            ->assertSee('search=TRX-PAGE');
    }

    public function test_owner_can_see_all_authorized_transactions(): void
    {
        $outletA = $this->defaultOutlet();
        $outletB = $this->createOutlet('Outlet B');
        $owner = $this->ownerUser();
        $cashierA = $this->cashierUser($outletA, 'Cashier A');
        $cashierB = $this->cashierUser($outletB, 'Cashier B');
        $product = $this->createProduct('HIS-060', 'Alpukat', 11000);

        $transactionA = $this->createTransactionRecord([
            'invoice_number' => 'TRX-OWN-A',
            'outlet_id' => $outletA->id,
            'user_id' => $cashierA->id,
            'subtotal' => 11000,
            'total' => 11000,
            'payment_method' => 'cash',
        ]);
        $this->attachItem($transactionA, $product, 1, 11000);

        $transactionB = $this->createTransactionRecord([
            'invoice_number' => 'TRX-OWN-B',
            'outlet_id' => $outletB->id,
            'user_id' => $cashierB->id,
            'subtotal' => 22000,
            'total' => 22000,
            'payment_method' => 'qris',
        ]);
        $this->attachItem($transactionB, $product, 2, 22000);

        $this->actingAs($owner)
            ->get('/riwayat-transaksi')
            ->assertOk()
            ->assertSee('TRX-OWN-A')
            ->assertDontSee('TRX-OWN-B');
    }
}
