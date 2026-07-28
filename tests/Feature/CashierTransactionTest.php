<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Outlet;
use App\Models\OutletProductStock;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashierTransactionTest extends TestCase
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
        $suffix = str()->random(6);

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

    private function createStock(Outlet $outlet, Product $product, float $quantity): OutletProductStock
    {
        return OutletProductStock::create([
            'outlet_id' => $outlet->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'minimum_stock' => 0,
        ]);
    }

    private function cashierUser(Outlet $outlet): User
    {
        return User::factory()->create([
            'name' => 'Cashier',
            'username' => 'cashier-'.str()->random(5),
            'email' => 'cashier-'.str()->random(5).'@terasfatimah.test',
            'role' => 'cashier',
            'outlet_id' => $outlet->id,
            'is_active' => true,
        ]);
    }

    public function test_cashier_can_open_cashier_page_and_see_own_outlet_products_only(): void
    {
        $cashierOutlet = $this->defaultOutlet();
        $cashier = $this->cashierUser($cashierOutlet);

        $ownProduct = $this->createProduct('BRG-CASH-001', 'Mangga Harum', 15000);

        $this->createStock($cashierOutlet, $ownProduct, 4);

        $this->actingAs($cashier)
            ->get('/kasir')
            ->assertOk()
            ->assertSee('Halaman Kasir')
            ->assertSee('Mangga Harum')
            ->assertDontSee('Apel Merah');
    }

    public function test_cash_checkout_succeeds_and_shows_receipt_page(): void
    {
        $cashierOutlet = $this->defaultOutlet();
        $cashier = $this->cashierUser($cashierOutlet);
        $product = $this->createProduct('BRG-CASH-010', 'Semangka', 10000);
        $this->createStock($cashierOutlet, $product, 5);

        $response = $this->actingAs($cashier)->post('/kasir', [
            'payment_method' => 'cash',
            'cash_received' => 10000,
            'items' => [
                [
                    'product_id' => $product->id,
                    'weight_grams' => 500,
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Transaksi berhasil disimpan.');

        $transaction = Transaction::query()->latest('id')->firstOrFail();
        $transaction->load('items');

        $this->assertSame('5000.00', $transaction->subtotal);
        $this->assertSame('5000.00', $transaction->total);
        $this->assertSame('10000.00', $transaction->cash_received);
        $this->assertSame('5000.00', $transaction->change_amount);
        $this->assertSame(1, $transaction->items()->count());
        $this->assertSame('4.500', OutletProductStock::where('outlet_id', $cashierOutlet->id)->where('product_id', $product->id)->firstOrFail()->quantity);

        $this->actingAs($cashier)
            ->get(route('kasir.show', $transaction))
            ->assertOk()
            ->assertSee($transaction->invoice_number)
            ->assertSee($transaction->user->name)
            ->assertSee('Kembali ke Kasir');
    }

    public function test_cash_checkout_rejects_when_cash_is_insufficient(): void
    {
        $cashierOutlet = $this->defaultOutlet();
        $cashier = $this->cashierUser($cashierOutlet);
        $product = $this->createProduct('BRG-CASH-020', 'Jeruk', 12000);
        $this->createStock($cashierOutlet, $product, 5);

        $this->actingAs($cashier)
            ->post('/kasir', [
                'payment_method' => 'cash',
                'cash_received' => 1000,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'weight_grams' => 500,
                    ],
                ],
            ])
            ->assertSessionHasErrors('cash_received');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_fake_frontend_price_is_ignored_by_server_recalculation(): void
    {
        $cashierOutlet = $this->defaultOutlet();
        $cashier = $this->cashierUser($cashierOutlet);
        $product = $this->createProduct('BRG-CASH-030', 'Nanas', 14000);
        $this->createStock($cashierOutlet, $product, 5);

        $this->actingAs($cashier)->post('/kasir', [
            'payment_method' => 'cash',
            'cash_received' => 10000,
            'items' => [
                [
                    'product_id' => $product->id,
                    'weight_grams' => 500,
                    'price_per_unit' => 1,
                    'subtotal' => 1,
                ],
            ],
        ])->assertRedirect();

        $transaction = Transaction::query()->latest('id')->firstOrFail();
        $transaction->load('items');

        $this->assertSame('7000.00', $transaction->subtotal);
        $this->assertSame('7000.00', $transaction->total);
        $transactionItem = $transaction->items->first();
        $this->assertNotNull($transactionItem);
        $this->assertSame('14000.00', $transactionItem->price_per_unit);
        $this->assertSame('7000.00', $transactionItem->subtotal);
    }

    public function test_cashier_cannot_post_other_outlet_id(): void
    {
        $cashierOutlet = $this->defaultOutlet();
        $otherOutlet = $this->createOutlet('Outlet Lain');
        $cashier = $this->cashierUser($cashierOutlet);
        $product = $this->createProduct('BRG-CASH-040', 'Pisang', 8000);
        $this->createStock($cashierOutlet, $product, 5);
        $this->createStock($otherOutlet, $product, 5);

        $this->actingAs($cashier)
            ->post('/kasir', [
                'outlet_id' => $otherOutlet->id,
                'payment_method' => 'cash',
                'cash_received' => 5000,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'weight_grams' => 500,
                    ],
                ],
            ])
            ->assertRedirect();

        $transaction = Transaction::query()->latest('id')->firstOrFail();
        $this->assertSame($cashierOutlet->id, $transaction->outlet_id);
    }

    public function test_double_submit_is_blocked_for_identical_payload(): void
    {
        $cashierOutlet = $this->defaultOutlet();
        $cashier = $this->cashierUser($cashierOutlet);
        $product = $this->createProduct('BRG-CASH-050', 'Pepaya', 9000);
        $this->createStock($cashierOutlet, $product, 5);

        $payload = [
            'payment_method' => 'cash',
            'cash_received' => 5000,
            'items' => [
                [
                    'product_id' => $product->id,
                    'weight_grams' => 500,
                ],
            ],
        ];

        $this->actingAs($cashier)
            ->post('/kasir', $payload)
            ->assertRedirect();

        $this->actingAs($cashier)
            ->post('/kasir', $payload)
            ->assertSessionHasErrors('transaction');

        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('transaction_items', 1);
    }
}
