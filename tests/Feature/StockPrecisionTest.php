<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Outlet;
use App\Models\OutletProductStock;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StockPrecisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_decimal_adjustment_preserves_three_decimal_places(): void
    {
        $outlet = Outlet::firstOrCreate(['name' => Outlet::DEFAULT_NAME]);
        $user = User::factory()->create([
            'role' => 'owner',
            'outlet_id' => $outlet->id,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
        $suffix = str()->random(8);
        $category = Category::create(['name' => 'Buah '.$suffix, 'slug' => 'buah-'.$suffix, 'is_active' => true]);
        $unit = Unit::create(['name' => 'Kilogram '.$suffix, 'symbol' => 'kg'.$suffix, 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'code' => 'PREC-'.$suffix,
            'name' => 'Produk Presisi '.$suffix,
            'purchase_price' => '10000.00',
            'selling_price' => '15000.00',
            'is_active' => true,
        ]);
        OutletProductStock::create([
            'outlet_id' => $outlet->id,
            'product_id' => $product->id,
            'quantity' => '1.000',
            'minimum_stock' => '0.300',
        ]);

        app(StockService::class)->adjustStock($user, $outlet, $product, 'adjustment_out', '0.100', 'Uji presisi');

        $this->assertSame('0.900', OutletProductStock::firstOrFail()->quantity);
        $this->assertDatabaseHas('stock_histories', [
            'quantity_before' => '1.000',
            'quantity_change' => '-0.100',
            'quantity_after' => '0.900',
        ]);
    }
}
