<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Outlet;
use App\Models\OutletProductStock;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Unit;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashierSearchAndReceiptTest extends TestCase
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

    private function createCategory(string $name): Category
    {
        $suffix = str()->random(5);

        return Category::create([
            'name' => $name.' '.$suffix,
            'slug' => str($name.' '.$suffix)->slug(),
            'description' => null,
            'is_active' => true,
        ]);
    }

    private function createUnit(): Unit
    {
        $suffix = str()->random(5);

        return Unit::create([
            'name' => 'Satuan '.$suffix,
            'symbol' => 'st'.$suffix,
            'is_active' => true,
        ]);
    }

    private function createProduct(string $code, string $name, Category $category, Unit $unit, float $sellingPrice): Product
    {
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

    public function test_cashier_can_search_products_by_name(): void
    {
        $outlet = $this->defaultOutlet();
        $cashier = $this->cashierUser($outlet);
        $category = $this->createCategory('Buah');
        $unit = $this->createUnit();

        $mangga = $this->createProduct('SRCH-001', 'Mangga Harum', $category, $unit, 15000);
        $apel = $this->createProduct('SRCH-002', 'Apel Merah', $category, $unit, 18000);
        $this->createStock($outlet, $mangga, 5);
        $this->createStock($outlet, $apel, 5);

        $this->actingAs($cashier)
            ->get('/kasir?search=Mangga')
            ->assertOk()
            ->assertSee('Mangga Harum')
            ->assertDontSee('Apel Merah');
    }

    public function test_cashier_can_filter_products_by_category(): void
    {
        $outlet = $this->defaultOutlet();
        $cashier = $this->cashierUser($outlet);
        $fruitCategory = $this->createCategory('Buah');
        $nonFruitCategory = $this->createCategory('Snack');
        $unit = $this->createUnit();

        $buah = $this->createProduct('SRCH-010', 'Nanas Madu', $fruitCategory, $unit, 12000);
        $snack = $this->createProduct('SRCH-011', 'Keripik', $nonFruitCategory, $unit, 9000);
        $this->createStock($outlet, $buah, 5);
        $this->createStock($outlet, $snack, 5);

        $this->actingAs($cashier)
            ->get('/kasir?category_id='.$fruitCategory->id)
            ->assertOk()
            ->assertSee('Nanas Madu')
            ->assertDontSee('Keripik');
    }

    public function test_zero_stock_products_do_not_appear_in_cashier_page(): void
    {
        $outlet = $this->defaultOutlet();
        $cashier = $this->cashierUser($outlet);
        $category = $this->createCategory('Buah');
        $unit = $this->createUnit();

        $available = $this->createProduct('SRCH-020', 'Semangka', $category, $unit, 10000);
        $soldOut = $this->createProduct('SRCH-021', 'Pepaya', $category, $unit, 11000);
        $this->createStock($outlet, $available, 3);
        $this->createStock($outlet, $soldOut, 0);

        $this->actingAs($cashier)
            ->get('/kasir')
            ->assertOk()
            ->assertSee('Semangka')
            ->assertDontSee('Pepaya');
    }

    public function test_authorized_user_can_open_printable_receipt_page(): void
    {
        $outlet = $this->defaultOutlet();
        $cashier = $this->cashierUser($outlet);
        $category = $this->createCategory('Buah');
        $unit = $this->createUnit();
        $product = $this->createProduct('PRN-001', 'Pisang', $category, $unit, 10000);
        $this->createStock($outlet, $product, 5);

        $transaction = app(TransactionService::class)->store($cashier, [
            'payment_method' => 'cash',
            'cash_received' => 10000,
            'items' => [
                [
                    'product_id' => $product->id,
                    'weight_grams' => 500,
                ],
            ],
        ]);

        $this->actingAs($cashier)
            ->get(route('kasir.print', $transaction))
            ->assertOk()
            ->assertSee($transaction->invoice_number)
            ->assertSee('Pisang')
            ->assertSee('Rp 5.000')
            ->assertSee('Rp 10.000');
    }

    public function test_cashier_cannot_print_other_outlet_transaction(): void
    {
        $cashierOutlet = $this->createOutlet('Outlet Kasir');
        $otherOutlet = $this->createOutlet('Outlet Lain');
        $cashier = $this->cashierUser($cashierOutlet);
        $category = $this->createCategory('Buah');
        $unit = $this->createUnit();
        $product = $this->createProduct('PRN-002', 'Alpukat', $category, $unit, 20000);
        $this->createStock($otherOutlet, $product, 5);

        $transaction = Transaction::create([
            'invoice_number' => 'TRX-20260724-9999',
            'outlet_id' => $otherOutlet->id,
            'user_id' => $cashier->id,
            'subtotal' => 20000,
            'discount' => 0,
            'total' => 20000,
            'payment_method' => 'cash',
            'cash_received' => 20000,
            'change_amount' => 0,
            'status' => 'completed',
        ]);

        TransactionItem::create([
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'quantity' => 1.000,
            'weight_grams' => 1000,
            'price_per_unit' => 20000,
            'subtotal' => 20000,
        ]);

        $this->actingAs($cashier)
            ->get(route('kasir.print', $transaction))
            ->assertForbidden();
    }
}
