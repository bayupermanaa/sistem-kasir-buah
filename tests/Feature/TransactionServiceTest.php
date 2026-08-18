<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Outlet;
use App\Models\OutletProductStock;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
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
            'name' => 'Buah Segar '.$suffix,
            'slug' => 'buah-segar-'.$suffix,
            'description' => null,
            'is_active' => true,
        ]);

        $unit = Unit::create([
            'name' => 'Kilogram '.$suffix,
            'symbol' => 'kg'.$suffix,
            'is_active' => true,
        ]);

        return Product::create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'code' => $code,
            'name' => $name,
            'description' => null,
            'image' => null,
            'purchase_price' => $sellingPrice - 2000,
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

    private function ownerUser(): User
    {
        return User::factory()->create([
            'name' => 'Owner',
            'username' => 'owner',
            'email' => 'owner@terasfatimah.test',
            'role' => 'owner',
            'outlet_id' => null,
            'is_active' => true,
        ]);
    }

    private function cashierUser(Outlet $outlet): User
    {
        return User::factory()->create([
            'name' => 'Cashier',
            'username' => 'cashier',
            'email' => 'cashier@terasfatimah.test',
            'role' => 'cashier',
            'outlet_id' => $outlet->id,
            'is_active' => true,
        ]);
    }

    public function test_invoice_number_is_unique(): void
    {
        $outlet = $this->defaultOutlet();
        $user = $this->ownerUser();
        $product = $this->createProduct('BRG-001', 'Mangga', 15000);
        $this->createStock($outlet, $product, 20);

        $service = app(TransactionService::class);

        $first = $service->store($user, [
            'outlet_id' => $outlet->id,
            'payment_method' => 'qris',
            'discount' => 0,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $second = $service->store($user, [
            'outlet_id' => $outlet->id,
            'payment_method' => 'qris',
            'discount' => 0,
            'cash_received' => 1,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $this->assertNotSame($first->invoice_number, $second->invoice_number);
        $this->assertMatchesRegularExpression('/^TRX-\d{8}-\d{4}$/', $first->invoice_number);
        $this->assertMatchesRegularExpression('/^TRX-\d{8}-\d{4}$/', $second->invoice_number);
    }

    public function test_transaction_success_reduces_stock_and_uses_server_side_totals(): void
    {
        $outlet = $this->defaultOutlet();
        $user = $this->ownerUser();
        $product = $this->createProduct('BRG-002', 'Apel', 15000);
        $this->createStock($outlet, $product, 10);

        $service = app(TransactionService::class);

        $transaction = $service->store($user, [
            'outlet_id' => $outlet->id,
            'payment_method' => 'qris',
            'discount' => 5000,
            'subtotal' => 1,
            'total' => 1,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'subtotal' => 1],
            ],
        ]);

        $this->assertSame('TRX-', substr($transaction->invoice_number, 0, 4));
        $this->assertSame('30000.00', $transaction->subtotal);
        $this->assertSame('25000.00', $transaction->total);
        $this->assertSame('5000.00', $transaction->discount);
        $this->assertNull($transaction->cash_received);
        $this->assertNull($transaction->change_amount);
        $this->assertSame(1, $transaction->items->count());
        $transactionItem = $transaction->items->first();
        $this->assertNotNull($transactionItem);
        $this->assertSame('8.000', OutletProductStock::where('outlet_id', $outlet->id)->where('product_id', $product->id)->firstOrFail()->quantity);
        $this->assertSame('2.000', $transactionItem->quantity);
        $this->assertSame('15000.00', $transactionItem->price_per_unit);
        $this->assertSame('30000.00', $transactionItem->subtotal);
    }

    public function test_transaction_supports_kilogram_quantity_and_percentage_discount(): void
    {
        $outlet = $this->defaultOutlet();
        $user = $this->ownerUser();
        $product = $this->createProduct('BRG-013', 'Mangga Manis', 12000);
        $this->createStock($outlet, $product, 10);

        $service = app(TransactionService::class);

        $transaction = $service->store($user, [
            'outlet_id' => $outlet->id,
            'payment_method' => 'qris',
            'discount_type' => 'percent',
            'discount_value' => 25,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 0.5],
            ],
        ]);

        $this->assertSame('6000.00', $transaction->subtotal);
        $this->assertSame('1500.00', $transaction->discount);
        $this->assertSame('4500.00', $transaction->total);
        $this->assertSame('qris', $transaction->payment_method);
        $this->assertNull($transaction->cash_received);
        $this->assertNull($transaction->change_amount);
        $transactionItem = $transaction->items->first();
        $this->assertNotNull($transactionItem);
        $this->assertSame('0.500', $transactionItem->quantity);
        $this->assertSame('6000.00', $transactionItem->subtotal);
    }
    public function test_transaction_rejects_when_stock_is_not_enough(): void
    {
        $outlet = $this->defaultOutlet();
        $user = $this->ownerUser();
        $product = $this->createProduct('BRG-003', 'Jeruk', 12000);
        $this->createStock($outlet, $product, 1);

        $service = app(TransactionService::class);

        $this->expectException(ValidationException::class);

        $service->store($user, [
            'outlet_id' => $outlet->id,
            'payment_method' => 'qris',
            'discount' => 0,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);
    }

    public function test_subtotal_is_calculated_from_server_values_not_frontend_payload(): void
    {
        $outlet = $this->defaultOutlet();
        $user = $this->ownerUser();
        $product = $this->createProduct('BRG-004', 'Semangka', 10000);
        $this->createStock($outlet, $product, 5);

        $service = app(TransactionService::class);

        $transaction = $service->store($user, [
            'outlet_id' => $outlet->id,
            'payment_method' => 'qris',
            'discount' => 0,
            'subtotal' => 1,
            'total' => 1,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'subtotal' => 1],
            ],
        ]);

        $this->assertSame('10000.00', $transaction->subtotal);
        $this->assertSame('10000.00', $transaction->total);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'subtotal' => '10000.00',
            'total' => '10000.00',
        ]);
    }

    public function test_transaction_rolls_back_when_later_item_fails(): void
    {
        $outlet = $this->defaultOutlet();
        $user = $this->ownerUser();
        $firstProduct = $this->createProduct('BRG-005', 'Pepaya', 8000);
        $secondProduct = $this->createProduct('BRG-006', 'Mangga', 15000);
        $this->createStock($outlet, $firstProduct, 5);
        $this->createStock($outlet, $secondProduct, 1);

        $service = app(TransactionService::class);

        try {
            $service->store($user, [
                'outlet_id' => $outlet->id,
                'payment_method' => 'qris',
                'discount' => 0,
                'items' => [
                    ['product_id' => $firstProduct->id, 'quantity' => 1],
                    ['product_id' => $secondProduct->id, 'quantity' => 2],
                ],
            ]);

            $this->fail('Transaksi seharusnya gagal dan rollback.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items.1.product_id', $exception->errors());
        }

        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('transaction_items', 0);
        $this->assertSame('5.000', OutletProductStock::where('outlet_id', $outlet->id)->where('product_id', $firstProduct->id)->firstOrFail()->quantity);
        $this->assertSame('1.000', OutletProductStock::where('outlet_id', $outlet->id)->where('product_id', $secondProduct->id)->firstOrFail()->quantity);
    }

    public function test_cashier_only_uses_own_outlet_stock(): void
    {
        $cashierOutlet = $this->defaultOutlet();
        $otherOutlet = $this->createOutlet('Outlet Lain');
        $cashier = $this->cashierUser($cashierOutlet);
        $product = $this->createProduct('BRG-007', 'Jambu', 9000);
        $this->createStock($cashierOutlet, $product, 4);
        $this->createStock($otherOutlet, $product, 10);

        $service = app(TransactionService::class);

        $transaction = $service->store($cashier, [
            'outlet_id' => $otherOutlet->id,
            'payment_method' => 'qris',
            'discount' => 0,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $this->assertSame($cashierOutlet->id, $transaction->outlet_id);
        $this->assertSame('2.000', OutletProductStock::where('outlet_id', $cashierOutlet->id)->where('product_id', $product->id)->firstOrFail()->quantity);
        $this->assertSame('10.000', OutletProductStock::where('outlet_id', $otherOutlet->id)->where('product_id', $product->id)->firstOrFail()->quantity);
    }
}
