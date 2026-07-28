<?php

namespace Database\Seeders;

use App\Models\OutletProductStock;
use App\Models\Product;
use App\Models\User;
use App\Services\StockService;
use App\Services\SingleOutletService;
use Illuminate\Database\Seeder;

class StockSeeder extends Seeder
{
    public function run(): void
    {
        $outlet = app(SingleOutletService::class)->getDefaultOutlet();
        $owner = User::where('role', 'owner')->firstOrFail();
        $service = app(StockService::class);

        $stockSeeds = [
            ['code' => 'BRG-0001', 'quantity' => 24.500, 'minimum' => 5.000],
            ['code' => 'BRG-0002', 'quantity' => 12.000, 'minimum' => 4.000],
            ['code' => 'BRG-0003', 'quantity' => 30.000, 'minimum' => 6.000],
            ['code' => 'BRG-0004', 'quantity' => 8.000, 'minimum' => 3.000],
            ['code' => 'BRG-0005', 'quantity' => 15.750, 'minimum' => 4.500],
            ['code' => 'BRG-0006', 'quantity' => 6.250, 'minimum' => 2.000],
            ['code' => 'BRG-0007', 'quantity' => 9.000, 'minimum' => 2.000],
            ['code' => 'BRG-0008', 'quantity' => 3.500, 'minimum' => 2.500],
        ];

        foreach ($stockSeeds as $stockSeed) {
            $product = Product::where('code', $stockSeed['code'])->firstOrFail();

            if (OutletProductStock::where('outlet_id', $outlet->id)->where('product_id', $product->id)->exists()) {
                continue;
            }

            $service->initializeStock(
                user: $owner,
                outlet: $outlet,
                product: $product,
                quantity: $stockSeed['quantity'],
                minimumStock: $stockSeed['minimum'],
                reason: 'Stok awal seed',
            );
        }
    }
}
