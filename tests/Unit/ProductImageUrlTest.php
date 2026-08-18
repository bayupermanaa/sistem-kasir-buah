<?php

namespace Tests\Unit;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductImageUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_url_normalizes_relative_and_legacy_paths(): void
    {
        $relative = Product::make(['image' => 'products/demo.webp']);
        $storageRelative = Product::make(['image' => 'storage/products/demo.webp']);
        $slashRelative = Product::make(['image' => '/storage/products/demo.webp']);
        $legacyWindows = Product::make(['image' => 'C:\\xampp\\htdocs\\sistem-kasir-buah\\storage\\app\\public\\products\\demo.webp']);
        $absoluteUrl = Product::make(['image' => 'http://example.test/storage/products/demo.webp']);

        $this->assertSame('http://localhost/storage/products/demo.webp', $relative->image_url);
        $this->assertSame('http://localhost/storage/products/demo.webp', $storageRelative->image_url);
        $this->assertSame('http://localhost/storage/products/demo.webp', $slashRelative->image_url);
        $this->assertSame('http://localhost/storage/products/demo.webp', $legacyWindows->image_url);
        $this->assertSame('http://example.test/storage/products/demo.webp', $absoluteUrl->image_url);
    }

    public function test_normalized_image_path_returns_relative_storage_path(): void
    {
        $product = Product::make(['image' => 'C:\\xampp\\htdocs\\sistem-kasir-buah\\storage\\app\\public\\products\\demo.webp']);

        $this->assertSame('products/demo.webp', $product->normalizedImagePath());
    }
}
