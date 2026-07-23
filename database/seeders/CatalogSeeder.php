<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Buah Lokal',
            'Buah Import',
            'Buah Potong',
            'Paket Hampers',
        ];

        foreach ($categories as $categoryName) {
            Category::firstOrCreate(
                ['name' => $categoryName],
                [
                    'slug' => Str::slug($categoryName),
                    'description' => null,
                    'is_active' => true,
                ]
            );
        }

        $units = [
            ['name' => 'Kilogram', 'symbol' => 'kg'],
            ['name' => 'Gram', 'symbol' => 'g'],
            ['name' => 'Pieces', 'symbol' => 'pcs'],
        ];

        foreach ($units as $unit) {
            Unit::firstOrCreate(
                ['name' => $unit['name']],
                [
                    'symbol' => $unit['symbol'],
                    'is_active' => true,
                ]
            );
        }

        $fruitCategory = Category::where('name', 'Buah Lokal')->first();
        $importCategory = Category::where('name', 'Buah Import')->first();
        $cutCategory = Category::where('name', 'Buah Potong')->first();
        $hampersCategory = Category::where('name', 'Paket Hampers')->first();
        $kgUnit = Unit::where('symbol', 'kg')->first();
        $gramUnit = Unit::where('symbol', 'g')->first();
        $pcsUnit = Unit::where('symbol', 'pcs')->first();

        $products = [
            ['code' => 'BRG-0001', 'name' => 'Pisang Cavendish', 'category_id' => $importCategory?->id, 'unit_id' => $kgUnit?->id, 'purchase_price' => 18000, 'selling_price' => 25000],
            ['code' => 'BRG-0002', 'name' => 'Apel Fuji', 'category_id' => $importCategory?->id, 'unit_id' => $kgUnit?->id, 'purchase_price' => 32000, 'selling_price' => 42000],
            ['code' => 'BRG-0003', 'name' => 'Jeruk Pontianak', 'category_id' => $fruitCategory?->id, 'unit_id' => $kgUnit?->id, 'purchase_price' => 14000, 'selling_price' => 22000],
            ['code' => 'BRG-0004', 'name' => 'Semangka Merah', 'category_id' => $fruitCategory?->id, 'unit_id' => $kgUnit?->id, 'purchase_price' => 12000, 'selling_price' => 18000],
            ['code' => 'BRG-0005', 'name' => 'Melon Premium', 'category_id' => $importCategory?->id, 'unit_id' => $kgUnit?->id, 'purchase_price' => 17000, 'selling_price' => 26000],
            ['code' => 'BRG-0006', 'name' => 'Potongan Buah Segar', 'category_id' => $cutCategory?->id, 'unit_id' => $gramUnit?->id, 'purchase_price' => 10000, 'selling_price' => 15000],
            ['code' => 'BRG-0007', 'name' => 'Paket Hampers Kecil', 'category_id' => $hampersCategory?->id, 'unit_id' => $pcsUnit?->id, 'purchase_price' => 50000, 'selling_price' => 75000],
            ['code' => 'BRG-0008', 'name' => 'Nanas Madu', 'category_id' => $fruitCategory?->id, 'unit_id' => $kgUnit?->id, 'purchase_price' => 11000, 'selling_price' => 17000],
        ];

        foreach ($products as $product) {
            Product::firstOrCreate(
                ['code' => $product['code']],
                [
                    'category_id' => $product['category_id'],
                    'unit_id' => $product['unit_id'],
                    'name' => $product['name'],
                    'description' => null,
                    'image' => null,
                    'purchase_price' => $product['purchase_price'],
                    'selling_price' => $product['selling_price'],
                    'is_active' => true,
                ]
            );
        }
    }
}
