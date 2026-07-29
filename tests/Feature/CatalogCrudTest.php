<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Outlet;
use App\Models\OutletProductStock;
use App\Models\StockHistory;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Unit;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogCrudTest extends TestCase
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
            'username' => 'owner',
            'email' => 'owner@terasfatimah.test',
            'role' => 'owner',
            'outlet_id' => $this->defaultOutlet()->id,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
    }

    private function cashierUser(): User
    {
        $outlet = $this->defaultOutlet();

        return User::factory()->create([
            'name' => 'Cashier',
            'username' => 'cashier',
            'email' => 'cashier@terasfatimah.test',
            'role' => 'cashier',
            'outlet_id' => $outlet->id,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
    }

    private function catalogSeed(): array
    {
        $category = Category::create([
            'name' => 'Buah Lokal',
            'description' => 'Kategori buah lokal',
            'is_active' => true,
        ]);

        $unit = Unit::create([
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'is_active' => true,
        ]);

        return [$category, $unit];
    }

    public function test_owner_can_crud_category(): void
    {
        $owner = $this->ownerUser();

        $this->actingAs($owner)->post('/categories', [
            'name' => 'Buah Organik',
            'description' => 'Kategori baru',
            'is_active' => 1,
        ])->assertRedirect(route('categories.index'));

        $category = Category::where('name', 'Buah Organik')->firstOrFail();
        $this->assertSame('buah-organik', $category->slug);

        $this->actingAs($owner)->put("/categories/{$category->id}", [
            'name' => 'Buah Organik Premium',
            'description' => 'Update kategori',
            'is_active' => 1,
        ])->assertRedirect(route('categories.index'));

        $category->refresh();
        $this->assertSame('buah-organik-premium', $category->slug);

        $this->actingAs($owner)->delete("/categories/{$category->id}")
            ->assertRedirect(route('categories.index'));

        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }

    public function test_owner_can_crud_unit(): void
    {
        $owner = $this->ownerUser();

        $this->actingAs($owner)->post('/units', [
            'name' => 'Box',
            'symbol' => 'box',
            'is_active' => 1,
        ])->assertRedirect(route('units.index'));

        $unit = Unit::where('name', 'Box')->firstOrFail();

        $this->actingAs($owner)->put("/units/{$unit->id}", [
            'name' => 'Box Besar',
            'symbol' => 'bx',
            'is_active' => 1,
        ])->assertRedirect(route('units.index'));

        $unit->refresh();
        $this->assertSame('Box Besar', $unit->name);

        $this->actingAs($owner)->delete("/units/{$unit->id}")
            ->assertRedirect(route('units.index'));

        $this->assertSoftDeleted('units', ['id' => $unit->id]);
    }

    public function test_owner_can_crud_product(): void
    {
        Storage::fake('public');
        $owner = $this->ownerUser();
        [$category, $unit] = $this->catalogSeed();

        $image = UploadedFile::fake()->image('buah.jpg');

        $this->actingAs($owner)->post('/barang-stok', [
            'code' => 'BRG-1001',
            'name' => 'Alpukat Premium',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 20000,
            'selling_price' => 28000,
            'description' => 'Produk contoh',
            'image' => $image,
            'is_active' => 1,
        ])->assertRedirect(route('products.index'))
            ->assertSessionHas('success', 'Produk berhasil ditambahkan.');

        $product = Product::where('code', 'BRG-1001')->firstOrFail();
        $this->assertSame(1, OutletProductStock::where('product_id', $product->id)->count());

        $this->actingAs($owner)->put("/barang-stok/{$product->id}", [
            'code' => 'BRG-1001',
            'name' => 'Alpukat Premium Besar',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 21000,
            'selling_price' => 30000,
            'description' => 'Produk diperbarui',
            'is_active' => 1,
        ])->assertRedirect(route('products.index'));

        $product->refresh();
        $this->assertSame('Alpukat Premium Besar', $product->name);

        $this->actingAs($owner)->delete("/barang-stok/{$product->id}")
            ->assertRedirect(route('products.index'));

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('outlet_product_stocks', ['product_id' => $product->id]);

        $this->actingAs($owner)
            ->get('/barang-stok')
            ->assertOk()
            ->assertDontSee('Alpukat Premium Besar');
    }

    public function test_product_creation_bootstraps_stock_for_default_outlet(): void
    {
        $owner = $this->ownerUser();
        [$category, $unit] = $this->catalogSeed();

        $this->actingAs($owner)->post('/barang-stok', [
            'code' => 'BRG-5001',
            'name' => 'Jeruk Mandalika',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 11000,
            'selling_price' => 16000,
            'description' => null,
            'is_active' => 1,
        ])->assertRedirect(route('products.index'))
            ->assertSessionHas('success', 'Produk berhasil ditambahkan.');

        $product = Product::where('code', 'BRG-5001')->firstOrFail();

        $this->assertDatabaseCount('outlet_product_stocks', 1);
        $this->assertSame(1, OutletProductStock::where('product_id', $product->id)->count());
        $this->assertDatabaseHas('outlet_product_stocks', [
            'product_id' => $product->id,
            'quantity' => '0.000',
            'minimum_stock' => '0.000',
        ]);
    }

    public function test_product_creation_rolls_back_when_stock_bootstrap_fails(): void
    {
        $owner = $this->ownerUser();
        [$category, $unit] = $this->catalogSeed();

        $this->app->instance(StockService::class, new class extends StockService
        {
            public function __construct()
            {
            }

            public function initializeProductStocks(Product $product): void
            {
                throw new \RuntimeException('gagal membuat stok');
            }
        });

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($owner)->post('/barang-stok', [
                'code' => 'BRG-6001',
                'name' => 'Pepaya Merah',
                'category_id' => $category->id,
                'unit_id' => $unit->id,
                'purchase_price' => 7000,
                'selling_price' => 12000,
                'description' => null,
                'is_active' => 1,
            ]);

            $this->fail('RuntimeException tidak terpanggil saat bootstrap stok gagal.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('gagal membuat stok', $exception->getMessage());
        }

        $this->assertDatabaseMissing('products', ['code' => 'BRG-6001']);
        $this->assertDatabaseCount('outlet_product_stocks', 0);
    }

    public function test_new_product_immediately_appears_on_barang_stok_page(): void
    {
        $owner = $this->ownerUser();
        [$category, $unit] = $this->catalogSeed();

        $this->actingAs($owner)->post('/barang-stok', [
            'code' => 'BRG-7001',
            'name' => 'Nanas Madu',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 9000,
            'selling_price' => 14000,
            'description' => null,
            'is_active' => 1,
        ])->assertRedirect(route('products.index'));

        $this->actingAs($owner)
            ->get('/barang-stok')
            ->assertOk()
            ->assertSee('BRG-7001')
            ->assertSee('Nanas Madu');
    }

    public function test_cashier_can_only_view_product_list(): void
    {
        [$category, $unit] = $this->catalogSeed();
        Product::create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'code' => 'BRG-2001',
            'name' => 'Jambu Merah',
            'description' => null,
            'image' => null,
            'purchase_price' => 10000,
            'selling_price' => 15000,
            'is_active' => true,
        ]);

        $cashier = $this->cashierUser();

        $this->actingAs($cashier)
            ->get('/barang-stok')
            ->assertOk()
            ->assertSee('Barang & Stok')
            ->assertDontSee('Tambah Produk');

        $this->actingAs($cashier)->get('/categories')->assertForbidden();
        $this->actingAs($cashier)->get('/units')->assertForbidden();
        $this->actingAs($cashier)->get('/barang-stok/create')->assertForbidden();
    }

    public function test_product_code_must_be_unique(): void
    {
        $owner = $this->ownerUser();
        [$category, $unit] = $this->catalogSeed();

        Product::create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'code' => 'BRG-3001',
            'name' => 'Mangga Harum Manis',
            'description' => null,
            'image' => null,
            'purchase_price' => 12000,
            'selling_price' => 18000,
            'is_active' => true,
        ]);

        $this->actingAs($owner)->post('/barang-stok', [
            'code' => 'BRG-3001',
            'name' => 'Mangga Gedong',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 13000,
            'selling_price' => 19000,
            'description' => null,
            'is_active' => 1,
        ])->assertSessionHasErrors('code');
    }

    public function test_product_image_validation_rejects_invalid_file(): void
    {
        $owner = $this->ownerUser();
        [$category, $unit] = $this->catalogSeed();

        $this->actingAs($owner)->post('/barang-stok', [
            'code' => 'BRG-4001',
            'name' => 'Semangka Mini',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 9000,
            'selling_price' => 14000,
            'description' => null,
            'image' => UploadedFile::fake()->create('dokumen.txt', 10, 'text/plain'),
            'is_active' => 1,
        ])->assertSessionHasErrors('image');
    }

    public function test_deleted_product_keeps_historical_records_intact(): void
    {
        $owner = $this->ownerUser();
        [$category, $unit] = $this->catalogSeed();

        $product = Product::create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'code' => 'BRG-8001',
            'name' => 'Pisang Raja',
            'description' => null,
            'image' => null,
            'purchase_price' => 10000,
            'selling_price' => 15000,
            'is_active' => true,
        ]);

        $transaction = Transaction::create([
            'invoice_number' => 'TRX-20260728-0001',
            'outlet_id' => $this->defaultOutlet()->id,
            'user_id' => $owner->id,
            'subtotal' => 15000,
            'discount' => 0,
            'total' => 15000,
            'payment_method' => 'cash',
            'cash_received' => 20000,
            'change_amount' => 5000,
            'status' => 'completed',
        ]);

        TransactionItem::create([
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'weight_grams' => 1000,
            'price_per_unit' => 15000,
            'subtotal' => 15000,
        ]);

        StockHistory::create([
            'outlet_id' => $this->defaultOutlet()->id,
            'product_id' => $product->id,
            'user_id' => $owner->id,
            'type' => 'initial_stock',
            'quantity_before' => 0,
            'quantity_change' => 1,
            'quantity_after' => 1,
            'reason' => 'Stok awal',
            'notes' => null,
        ]);

        $this->actingAs($owner)->delete("/barang-stok/{$product->id}")
            ->assertRedirect(route('products.index'));

        $transactionItem = TransactionItem::with('product')->firstOrFail();
        $stockHistory = StockHistory::with('product')->firstOrFail();

        $this->assertSame('Pisang Raja', $transactionItem->product?->name);
        $this->assertSame('Pisang Raja', $stockHistory->product?->name);
    }
}
