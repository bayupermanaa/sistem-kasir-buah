<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Outlet;
use App\Models\OutletProductStock;
use App\Models\Product;
use App\Models\StockHistory;
use App\Models\Unit;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Stage8D9RegressionTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $outlet = Outlet::firstOrCreate(['name' => 'Toko Buah Teras Fatimah']);

        return User::factory()->create([
            'role' => 'owner',
            'outlet_id' => $outlet->id,
            'is_active' => true,
        ]);


    }

    private function product(): Product
    {
        $suffix = str()->random(6);
        $category = Category::create(['name' => 'Buah '.$suffix, 'slug' => 'buah-'.$suffix, 'is_active' => true]);
        $unit = Unit::create(['name' => 'Kilogram '.$suffix, 'symbol' => 'kg', 'is_active' => true]);

        return Product::create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'code' => 'REG-'.$suffix,
            'name' => 'Produk Regression '.$suffix,
            'purchase_price' => null,
            'selling_price' => 15000,
            'is_active' => true,
        ]);
    }

    public function test_stock_history_filter_uses_simple_groups_and_hides_return_option(): void
    {
        $owner = $this->owner();
        $product = $this->product();
        $outlet = $owner->outlet;
        $stock = OutletProductStock::create(['outlet_id' => $outlet->id, 'product_id' => $product->id, 'quantity' => 10, 'minimum_stock' => 0]);

        foreach (['initial_stock', 'sale', 'adjustment_in', 'stock_opname', 'return_in'] as $type) {
            StockHistory::create([
                'outlet_id' => $outlet->id,
                'product_id' => $product->id,
                'user_id' => $owner->id,
                'type' => $type,
                'quantity_before' => 10,
                'quantity_change' => 1,
                'quantity_after' => 11,
                'reason' => $type === 'stock_opname' ? 'Selisih hasil pemeriksaan stok' : $type,
            ]);
        }

        $this->actingAs($owner)->get('/riwayat-stok?type=incoming')->assertOk()->assertSee('Stok Awal')->assertDontSee('bi-cart-check');
        $this->actingAs($owner)->get('/riwayat-stok?type=outgoing')->assertOk()->assertSee('Penjualan')->assertDontSee('bi-box-seam me-1');
        $this->actingAs($owner)->get('/riwayat-stok?type=adjustment')->assertOk()->assertSee('Koreksi Tambah');
        $this->actingAs($owner)->get('/riwayat-stok?type=stock_opname')->assertOk()->assertSee('Selisih hasil pemeriksaan stok');
        $this->actingAs($owner)->get('/riwayat-stok')->assertOk()->assertDontSee('value=return_in');
    }

    public function test_product_without_purchase_price_can_be_created_and_sold_without_fake_profit(): void
    {
        $owner = $this->owner();
        $product = $this->product();
        OutletProductStock::create(['outlet_id' => $owner->outlet_id, 'product_id' => $product->id, 'quantity' => 5, 'minimum_stock' => 0]);

        $transaction = app(TransactionService::class)->store($owner, [
            'payment_method' => 'cash',
            'cash_received' => 15000,
            'items' => [['product_id' => $product->id, 'weight_grams' => 1000]],
        ]);

        $this->assertSame('15000.00', $transaction->total);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'purchase_price' => null]);
        $this->actingAs($owner)->get(route('reports.profit-loss'))->assertOk()->assertSee('Perhitungan laba belum lengkap')->assertSee('Belum dapat dihitung');
    }

    public function test_owner_can_record_stock_purchase_without_expense_double_entry(): void
    {
        $owner = $this->owner();
        $product = $this->product();
        $stock = OutletProductStock::create(['outlet_id' => $owner->outlet_id, 'product_id' => $product->id, 'quantity' => 2, 'minimum_stock' => 0]);

        $this->actingAs($owner)->post(route('stocks.purchase.store', $product), [
            'quantity' => 3.5,
            'unit_cost' => 10000,
            'purchase_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'notes' => 'Pembelian buah mingguan',
        ])->assertRedirect(route('products.index'));

        $this->assertSame('5.500', $stock->refresh()->quantity);
        $this->assertDatabaseHas('stock_purchases', ['product_id' => $product->id, 'total_cost' => '35000.00']);
        $this->assertDatabaseHas('stock_histories', ['product_id' => $product->id, 'type' => 'restock', 'reason' => 'Pembelian stok']);
        $this->assertDatabaseMissing('expenses', ['description' => 'Pembelian buah mingguan']);
        $this->actingAs($owner)->get(route('reports.cash-flow'))->assertOk()->assertSee('Pembelian Stok')->assertSee('Rp 35.000');
    }
}
