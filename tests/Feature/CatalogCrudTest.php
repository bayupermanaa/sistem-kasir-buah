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

        $product = Product::where('name', 'Alpukat Premium')->firstOrFail();
        $this->assertSame('BL-0001', $product->code);
        $this->assertNotNull($product->image);
        $this->assertStringStartsWith('products/', $product->image);
        Storage::disk('public')->assertExists($product->image);
        $this->assertStringContainsString('/storage/products/', $product->image_url ?? '');
        $this->assertSame(1, OutletProductStock::where('product_id', $product->id)->count());

        $this->actingAs($owner)->put("/barang-stok/{$product->id}", [
            'code' => 'BRG-9999',
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
        $this->assertSame('BL-0001', $product->code);

        $this->actingAs($owner)->delete("/barang-stok/{$product->id}")
            ->assertRedirect(route('products.index'));

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('outlet_product_stocks', ['product_id' => $product->id]);
        Storage::disk('public')->assertExists($product->image);

        $this->actingAs($owner)
            ->get('/barang-stok')
            ->assertOk()
            ->assertDontSee('Alpukat Premium Besar');
    }

    public function test_product_edit_without_new_image_keeps_existing_image(): void
    {
        Storage::fake('public');
        $owner = $this->ownerUser();
        [$category, $unit] = $this->catalogSeed();

        $initialImage = UploadedFile::fake()->image('awal.jpg', 600, 600)->size(800);

        $this->actingAs($owner)->post('/barang-stok', [
            'name' => 'Jeruk Bali',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 12000,
            'selling_price' => 18000,
            'description' => null,
            'image' => $initialImage,
            'is_active' => 1,
        ])->assertRedirect(route('products.index'));

        $product = Product::where('name', 'Jeruk Bali')->firstOrFail();
        $originalImage = $product->image;

        $this->actingAs($owner)->put("/barang-stok/{$product->id}", [
            'name' => 'Jeruk Bali Revisi',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 12500,
            'selling_price' => 18500,
            'description' => 'Tanpa foto baru',
            'is_active' => 1,
        ])->assertRedirect(route('products.index'));

        $product->refresh();

        $this->assertSame($originalImage, $product->image);
        $this->assertNotNull($product->image_url);
        Storage::disk('public')->assertExists($originalImage);
    }

    public function test_product_edit_with_new_image_uses_new_file_and_keeps_old_one(): void
    {
        Storage::fake('public');
        $owner = $this->ownerUser();
        [$category, $unit] = $this->catalogSeed();

        $firstImage = UploadedFile::fake()->image('pertama.jpg', 600, 600)->size(800);
        $secondImage = UploadedFile::fake()->image('kedua.jpg', 600, 600)->size(900);

        $this->actingAs($owner)->post('/barang-stok', [
            'name' => 'Mangga Harum',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 15000,
            'selling_price' => 22000,
            'description' => null,
            'image' => $firstImage,
            'is_active' => 1,
        ])->assertRedirect(route('products.index'));

        $product = Product::where('name', 'Mangga Harum')->firstOrFail();
        $firstPath = $product->image;

        $this->actingAs($owner)->put("/barang-stok/{$product->id}", [
            'name' => 'Mangga Harum Revisi',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 15500,
            'selling_price' => 22500,
            'description' => 'Foto baru',
            'image' => $secondImage,
            'is_active' => 1,
        ])->assertRedirect(route('products.index'));

        $product->refresh();

        $this->assertNotSame($firstPath, $product->image);
        Storage::disk('public')->assertExists($firstPath);
        Storage::disk('public')->assertExists($product->image);
    }

    public function test_product_update_persists_active_toggle_state(): void
    {
        $owner = $this->ownerUser();
        [$category, $unit] = $this->catalogSeed();

        $this->actingAs($owner)->post('/barang-stok', [
            'name' => 'Delima Merah',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 17000,
            'selling_price' => 24000,
            'description' => null,
            'is_active' => 1,
        ])->assertRedirect(route('products.index'));

        $product = Product::where('name', 'Delima Merah')->firstOrFail();
        $this->assertTrue($product->is_active);

        $this->actingAs($owner)->put("/barang-stok/{$product->id}", [
            'name' => 'Delima Merah',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 17000,
            'selling_price' => 24000,
            'description' => null,
        ])->assertRedirect(route('products.index'));

        $product->refresh();
        $this->assertFalse($product->is_active);

        $this->actingAs($owner)->put("/barang-stok/{$product->id}", [
            'name' => 'Delima Merah',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 17000,
            'selling_price' => 24000,
            'description' => null,
            'is_active' => 1,
        ])->assertRedirect(route('products.index'));

        $product->refresh();
        $this->assertTrue($product->is_active);
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

        $product = Product::where('name', 'Jeruk Mandalika')->firstOrFail();

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

        $this->assertDatabaseMissing('products', ['name' => 'Pepaya Merah']);
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

    public function test_product_code_is_auto_generated_and_increments_by_category(): void
    {
        $owner = $this->ownerUser();
        [$category, $unit] = $this->catalogSeed();
        $anotherCategory = Category::create([
            'name' => 'Buah Import',
            'description' => 'Kategori buah import',
            'is_active' => true,
        ]);

        $this->actingAs($owner)->post('/barang-stok', [
            'name' => 'Mangga Gedong',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 13000,
            'selling_price' => 19000,
            'description' => null,
            'is_active' => 1,
        ])->assertRedirect(route('products.index'));

        $this->actingAs($owner)->post('/barang-stok', [
            'name' => 'Mangga Manis',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 14000,
            'selling_price' => 20000,
            'description' => null,
            'is_active' => 1,
        ])->assertRedirect(route('products.index'));

        $this->actingAs($owner)->post('/barang-stok', [
            'name' => 'Kiwi Premium',
            'category_id' => $anotherCategory->id,
            'unit_id' => $unit->id,
            'purchase_price' => 15000,
            'selling_price' => 22000,
            'description' => null,
            'is_active' => 1,
        ])->assertRedirect(route('products.index'));

        $this->assertSame('BL-0001', Product::where('name', 'Mangga Gedong')->firstOrFail()->code);
        $this->assertSame('BL-0002', Product::where('name', 'Mangga Manis')->firstOrFail()->code);
        $this->assertSame('BI-0001', Product::where('name', 'Kiwi Premium')->firstOrFail()->code);
    }

    public function test_product_edit_does_not_change_existing_code_when_category_changes(): void
    {
        $owner = $this->ownerUser();
        [$category, $unit] = $this->catalogSeed();
        $newCategory = Category::create([
            'name' => 'Buah Potong',
            'description' => 'Kategori buah potong',
            'is_active' => true,
        ]);

        $this->actingAs($owner)->post('/barang-stok', [
            'name' => 'Semangka Besar',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 8000,
            'selling_price' => 12000,
            'description' => null,
            'is_active' => 1,
        ])->assertRedirect(route('products.index'));

        $product = Product::where('name', 'Semangka Besar')->firstOrFail();
        $originalCode = $product->code;

        $this->actingAs($owner)->put("/barang-stok/{$product->id}", [
            'code' => 'SHOULD-NOT-CHANGE',
            'name' => 'Semangka Besar Revisi',
            'category_id' => $newCategory->id,
            'unit_id' => $unit->id,
            'purchase_price' => 8500,
            'selling_price' => 12500,
            'description' => 'Edit kategori',
            'is_active' => 1,
        ])->assertRedirect(route('products.index'));

        $product->refresh();
        $this->assertSame($originalCode, $product->code);
    }

    public function test_stock_status_filters_work_for_safe_low_and_out(): void
    {
        $owner = $this->ownerUser();
        [$category, $unit] = $this->catalogSeed();

        $safe = Product::create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'code' => 'STK-001',
            'name' => 'Aman',
            'description' => null,
            'image' => null,
            'purchase_price' => 10000,
            'selling_price' => 15000,
            'is_active' => true,
        ]);
        $low = Product::create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'code' => 'STK-002',
            'name' => 'Menipis',
            'description' => null,
            'image' => null,
            'purchase_price' => 10000,
            'selling_price' => 15000,
            'is_active' => true,
        ]);
        $out = Product::create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'code' => 'STK-003',
            'name' => 'Habis',
            'description' => null,
            'image' => null,
            'purchase_price' => 10000,
            'selling_price' => 15000,
            'is_active' => true,
        ]);

        OutletProductStock::create([
            'outlet_id' => $this->defaultOutlet()->id,
            'product_id' => $safe->id,
            'quantity' => 5,
            'minimum_stock' => 2,
        ]);
        OutletProductStock::create([
            'outlet_id' => $this->defaultOutlet()->id,
            'product_id' => $low->id,
            'quantity' => 2,
            'minimum_stock' => 5,
        ]);
        OutletProductStock::create([
            'outlet_id' => $this->defaultOutlet()->id,
            'product_id' => $out->id,
            'quantity' => 0,
            'minimum_stock' => 2,
        ]);

        $this->actingAs($owner)
            ->get('/barang-stok?status=safe')
            ->assertOk()
            ->assertSee('STK-001')
            ->assertDontSee('STK-002')
            ->assertDontSee('STK-003');

        $this->actingAs($owner)
            ->get('/barang-stok?status=low')
            ->assertOk()
            ->assertSee('STK-002')
            ->assertDontSee('STK-001')
            ->assertDontSee('STK-003');

        $this->actingAs($owner)
            ->get('/barang-stok?status=out')
            ->assertOk()
            ->assertSee('STK-003')
            ->assertDontSee('STK-001')
            ->assertDontSee('STK-002');
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

        $this->actingAs($owner)->post('/barang-stok', [
            'code' => 'BRG-4002',
            'name' => 'Semangka Besar',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 9000,
            'selling_price' => 14000,
            'description' => null,
            'image' => UploadedFile::fake()->image('besar.jpg')->size(4096),
            'is_active' => 1,
        ])->assertSessionHasErrors('image');
    }

    public function test_products_without_image_show_placeholder_in_kasir_and_barang_stok(): void
    {
        $owner = $this->ownerUser();
        [$category, $unit] = $this->catalogSeed();

        Product::create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'code' => 'BRG-9001',
            'name' => 'Produk Tanpa Foto',
            'description' => null,
            'image' => null,
            'purchase_price' => 10000,
            'selling_price' => 15000,
            'is_active' => true,
        ]);

        $product = Product::where('code', 'BRG-9001')->firstOrFail();

        OutletProductStock::create([
            'outlet_id' => $this->defaultOutlet()->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'minimum_stock' => 0,
        ]);

        $this->actingAs($owner)
            ->get('/barang-stok')
            ->assertOk()
            ->assertSee('ui-product-thumb-placeholder');

        $this->actingAs($owner)
            ->get('/kasir')
            ->assertOk()
            ->assertSee('ui-product-placeholder');
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
