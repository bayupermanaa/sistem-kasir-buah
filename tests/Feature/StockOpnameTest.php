<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Outlet;
use App\Models\OutletProductStock;
use App\Models\Product;
use App\Models\StockHistory;
use App\Models\StockOpname;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StockOpnameTest extends TestCase
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

    private function ownerUser(): User
    {
        return User::factory()->create([
            'name' => 'Owner',
            'username' => 'owner-opname',
            'email' => 'owner-opname@terasfatimah.test',
            'role' => 'owner',
            'outlet_id' => $this->defaultOutlet()->id,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
    }

    private function cashierUser(): User
    {
        return User::factory()->create([
            'name' => 'Cashier',
            'username' => 'cashier-opname',
            'email' => 'cashier-opname@terasfatimah.test',
            'role' => 'cashier',
            'outlet_id' => $this->defaultOutlet()->id,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
    }

    private function createProduct(string $code, string $name): Product
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
            'symbol' => 'kg-'.$suffix,
            'is_active' => true,
        ]);

        return Product::create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'code' => $code,
            'name' => $name,
            'description' => null,
            'image' => null,
            'purchase_price' => 10000,
            'selling_price' => 15000,
            'is_active' => true,
        ]);
    }

    private function createStock(Product $product, float $quantity): OutletProductStock
    {
        return OutletProductStock::create([
            'outlet_id' => $this->defaultOutlet()->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'minimum_stock' => 0,
        ]);
    }

    public function test_owner_can_open_stock_opname_pages_and_cashier_is_denied(): void
    {
        $owner = $this->ownerUser();
        $cashier = $this->cashierUser();

        $this->actingAs($owner)->get(route('stock.opnames.index'))->assertOk()->assertSee('Daftar Stock Opname');
        $this->actingAs($owner)->get(route('stock.opnames.create'))->assertOk()->assertSee('Form Stock Opname');

        $this->actingAs($cashier)->get(route('stock.opnames.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('stock.opnames.create'))->assertForbidden();
    }

    public function test_stock_opname_can_be_created_and_adjusts_stock_history(): void
    {
        $owner = $this->ownerUser();
        $productA = $this->createProduct('OPN-001', 'Apel Fuji');
        $productB = $this->createProduct('OPN-002', 'Mangga Golek');
        $productC = $this->createProduct('OPN-003', 'Jeruk Pontianak');

        $this->createStock($productA, 10);
        $this->createStock($productB, 5);
        $this->createStock($productC, 7);

        $response = $this->actingAs($owner)->post(route('stock.opnames.store'), [
            'opname_date' => '2026-08-14',
            'notes' => 'Opname akhir minggu',
            'outlet_id' => 999,
            'items' => [
                [
                    'product_id' => $productA->id,
                    'physical_quantity' => 8.5,
                    'notes' => 'Selisih kurang',
                ],
                [
                    'product_id' => $productB->id,
                    'physical_quantity' => 6,
                    'notes' => 'Selisih lebih',
                ],
                [
                    'product_id' => $productC->id,
                    'physical_quantity' => 7,
                    'notes' => 'Sesuai',
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Stock Opname berhasil disimpan.');

        $stockOpname = StockOpname::with('items')->firstOrFail();
        $this->assertSame($this->defaultOutlet()->id, $stockOpname->outlet_id);
        $this->assertSame($owner->id, $stockOpname->created_by);
        $this->assertSame('Opname akhir minggu', $stockOpname->notes);
        $this->assertSame('completed', $stockOpname->status);
        $this->assertSame(3, $stockOpname->items->count());

        $this->assertDatabaseHas('stock_opname_items', [
            'stock_opname_id' => $stockOpname->id,
            'product_id' => $productA->id,
            'system_quantity' => '10.000',
            'physical_quantity' => '8.500',
            'difference' => '-1.500',
        ]);

        $this->assertDatabaseHas('stock_opname_items', [
            'stock_opname_id' => $stockOpname->id,
            'product_id' => $productB->id,
            'system_quantity' => '5.000',
            'physical_quantity' => '6.000',
            'difference' => '1.000',
        ]);

        $this->assertDatabaseHas('stock_opname_items', [
            'stock_opname_id' => $stockOpname->id,
            'product_id' => $productC->id,
            'system_quantity' => '7.000',
            'physical_quantity' => '7.000',
            'difference' => '0.000',
        ]);

        $this->assertSame('8.500', OutletProductStock::where('product_id', $productA->id)->firstOrFail()->quantity);
        $this->assertSame('6.000', OutletProductStock::where('product_id', $productB->id)->firstOrFail()->quantity);
        $this->assertSame('7.000', OutletProductStock::where('product_id', $productC->id)->firstOrFail()->quantity);

        $this->assertDatabaseCount('stock_histories', 2);
        $this->assertDatabaseHas('stock_histories', [
            'reference_type' => StockOpname::class,
            'reference_id' => $stockOpname->id,
            'product_id' => $productA->id,
            'type' => 'stock_opname',
            'quantity_before' => '10.000',
            'quantity_change' => '-1.500',
            'quantity_after' => '8.500',
        ]);
        $this->assertDatabaseHas('stock_histories', [
            'reference_type' => StockOpname::class,
            'reference_id' => $stockOpname->id,
            'product_id' => $productB->id,
            'type' => 'stock_opname',
            'quantity_before' => '5.000',
            'quantity_change' => '1.000',
            'quantity_after' => '6.000',
        ]);
        $this->assertDatabaseMissing('stock_histories', [
            'reference_type' => StockOpname::class,
            'reference_id' => $stockOpname->id,
            'product_id' => $productC->id,
        ]);

        $this->actingAs($owner)
            ->get(route('stock.opnames.show', $stockOpname))
            ->assertOk()
            ->assertSee('Apel Fuji')
            ->assertSee('Sesuai')
            ->assertSee('-1,500')
            ->assertSee('+1,000');

        $this->actingAs($owner)
            ->get('/riwayat-stok')
            ->assertOk()
            ->assertSee('Stock Opname');
    }

    public function test_stock_opname_cannot_be_processed_twice(): void
    {
        $owner = $this->ownerUser();
        $product = $this->createProduct('OPN-010', 'Semangka');
        $this->createStock($product, 10);

        $payload = [
            'opname_date' => '2026-08-14',
            'notes' => 'Double submit test',
            'items' => [
                [
                    'product_id' => $product->id,
                    'physical_quantity' => 9,
                    'notes' => null,
                ],
            ],
        ];

        $this->actingAs($owner)
            ->post(route('stock.opnames.store'), $payload)
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('stock.opnames.store'), $payload)
            ->assertSessionHasErrors('stock_opname');

        $this->assertDatabaseCount('stock_opnames', 1);
        $this->assertDatabaseCount('stock_opname_items', 1);
        $this->assertSame('9.000', OutletProductStock::where('product_id', $product->id)->firstOrFail()->quantity);
    }

    public function test_stock_opname_accepts_decimal_quantity_and_rejects_negative_values(): void
    {
        $owner = $this->ownerUser();
        $productA = $this->createProduct('OPN-020', 'Potongan Buah Segar');
        $productB = $this->createProduct('OPN-021', 'Campur Buah Premium');
        $this->createStock($productA, 6.249);
        $this->createStock($productB, 10.5);

        $this->actingAs($owner)->post(route('stock.opnames.store'), [
            'opname_date' => '2026-08-14',
            'notes' => null,
            'items' => [
                [
                    'product_id' => $productA->id,
                    'physical_quantity' => 6.249,
                    'notes' => 'Sama persis',
                ],
                [
                    'product_id' => $productB->id,
                    'physical_quantity' => 10.5,
                    'notes' => 'Setengah kilogram tambahan',
                ],
            ],
        ])->assertRedirect();

        $opname = StockOpname::with('items')->latest('id')->firstOrFail();
        $this->assertSame('6.249', $opname->items->firstWhere('product_id', $productA->id)->physical_quantity);
        $this->assertSame('0.000', $opname->items->firstWhere('product_id', $productA->id)->difference);
        $this->assertSame('10.500', $opname->items->firstWhere('product_id', $productB->id)->physical_quantity);
        $this->assertSame('0.000', $opname->items->firstWhere('product_id', $productB->id)->difference);

        $this->actingAs($owner)->post(route('stock.opnames.store'), [
            'opname_date' => '2026-08-14',
            'notes' => null,
            'items' => [
                [
                    'product_id' => $productA->id,
                    'physical_quantity' => -1,
                    'notes' => null,
                ],
            ],
        ])->assertSessionHasErrors('items.0.physical_quantity');
    }
}
