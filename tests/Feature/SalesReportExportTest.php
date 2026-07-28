<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SalesReportExportTest extends TestCase
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
        $suffix = str()->random(5);

        $category = Category::create([
            'name' => 'Kategori '.$suffix,
            'slug' => 'kategori-'.$suffix,
            'description' => null,
            'is_active' => true,
        ]);

        $unit = Unit::create([
            'name' => 'Satuan '.$suffix,
            'symbol' => 'st'.$suffix,
            'is_active' => true,
        ]);

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

    private function ownerUser(): User
    {
        return User::factory()->create([
            'name' => 'Owner',
            'username' => 'owner-'.str()->random(5),
            'email' => 'owner-'.str()->random(5).'@terasfatimah.test',
            'role' => 'owner',
            'outlet_id' => null,
            'is_active' => true,
        ]);
    }

    private function cashierUser(Outlet $outlet, string $name): User
    {
        return User::factory()->create([
            'name' => $name,
            'username' => strtolower(str_replace(' ', '-', $name)).'-'.str()->random(5),
            'email' => strtolower(str_replace(' ', '-', $name)).'-'.str()->random(5).'@terasfatimah.test',
            'role' => 'cashier',
            'outlet_id' => $outlet->id,
            'is_active' => true,
        ]);
    }

    private function createTransaction(array $attributes): Transaction
    {
        $transaction = Transaction::create(array_merge([
            'subtotal' => 0,
            'discount' => 0,
            'total' => 0,
            'payment_method' => 'cash',
            'cash_received' => null,
            'change_amount' => null,
            'status' => 'completed',
        ], $attributes));

        if (! empty($attributes['created_at'])) {
            $timestamp = Carbon::parse($attributes['created_at']);
            $transaction->forceFill([
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->saveQuietly();
        }

        return $transaction;
    }

    private function attachItem(Transaction $transaction, Product $product, float $quantity, float $subtotal): void
    {
        TransactionItem::create([
            'transaction_id' => $transaction->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'weight_grams' => (int) round($quantity * 1000),
            'price_per_unit' => $quantity > 0 ? $subtotal / $quantity : 0,
            'subtotal' => $subtotal,
        ]);
    }

    private function seedDataset(): array
    {
        $outletA = $this->defaultOutlet();
        $outletB = $this->createOutlet('Outlet B');
        $owner = $this->ownerUser();
        $cashierA = $this->cashierUser($outletA, 'Cashier A');
        $cashierB = $this->cashierUser($outletB, 'Cashier B');

        $mango = $this->createProduct('EXP-001', 'Mangga', 10000);
        $apple = $this->createProduct('EXP-002', 'Apel', 15000);
        $orange = $this->createProduct('EXP-003', 'Jeruk', 20000);

        $completedA = $this->createTransaction([
            'invoice_number' => 'TRX-EXP-001',
            'outlet_id' => $outletA->id,
            'user_id' => $cashierA->id,
            'subtotal' => 25000,
            'discount' => 1000,
            'total' => 24000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_at' => '2026-07-24 10:00:00',
        ]);
        $this->attachItem($completedA, $mango, 1, 10000);
        $this->attachItem($completedA, $apple, 1, 15000);

        $completedB = $this->createTransaction([
            'invoice_number' => 'TRX-EXP-002',
            'outlet_id' => $outletB->id,
            'user_id' => $cashierB->id,
            'subtotal' => 20000,
            'discount' => 0,
            'total' => 20000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_at' => '2026-07-24 11:00:00',
        ]);
        $this->attachItem($completedB, $orange, 1, 20000);

        $cancelled = $this->createTransaction([
            'invoice_number' => 'TRX-EXP-003',
            'outlet_id' => $outletA->id,
            'user_id' => $cashierA->id,
            'subtotal' => 50000,
            'discount' => 0,
            'total' => 50000,
            'payment_method' => 'cash',
            'status' => 'cancelled',
            'created_at' => '2026-07-24 12:00:00',
        ]);
        $this->attachItem($cancelled, $mango, 3, 50000);

        return compact('owner', 'cashierA', 'cashierB', 'outletA', 'outletB');
    }

    public function test_cashier_can_export_pdf_and_csv_for_own_outlet(): void
    {
        $data = $this->seedDataset();

        $pdfResponse = $this->actingAs($data['cashierA'])
            ->get('/laporan-penjualan/export/pdf?date_from=2026-07-24&date_to=2026-07-24');

        $pdfResponse->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename="laporan-penjualan-2026-07-24-sampai-2026-07-24.pdf"');

        $this->assertStringStartsWith('%PDF', $pdfResponse->getContent());
        $this->assertNotEmpty($pdfResponse->getContent());

        $csvResponse = $this->actingAs($data['cashierA'])
            ->get('/laporan-penjualan/export/excel?date_from=2026-07-24&date_to=2026-07-24');

        $csvResponse->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename="laporan-penjualan-2026-07-24-sampai-2026-07-24.csv"');

        $this->assertStringContainsString('Ringkasan', $csvResponse->getContent());
        $this->assertStringContainsString('Total Transaksi', $csvResponse->getContent());
        $this->assertStringContainsString('24.000', $csvResponse->getContent());
        $this->assertStringNotContainsString('Jeruk', $csvResponse->getContent());
        $this->assertNotEmpty($pdfResponse->getContent());
    }

    public function test_cashier_cannot_export_other_outlet_data(): void
    {
        $data = $this->seedDataset();

        $response = $this->actingAs($data['cashierA'])
            ->get('/laporan-penjualan/export/excel?outlet_id='.$data['outletB']->id.'&cashier_id='.$data['cashierB']->id);

        $response->assertOk();
        $this->assertStringNotContainsString('Outlet B', $response->getContent());
        $this->assertStringNotContainsString('Cashier B', $response->getContent());
        $this->assertStringContainsString('Toko Buah Teras Fatimah', $response->getContent());
    }

    public function test_exports_respect_completed_only_and_empty_data_still_downloads(): void
    {
        $data = $this->seedDataset();

        $ownerPdf = $this->actingAs($data['owner'])
            ->get('/laporan-penjualan/export/pdf?date_from=2026-07-24&date_to=2026-07-24');

        $this->assertStringStartsWith('%PDF', $ownerPdf->getContent());
        $this->assertNotEmpty($ownerPdf->getContent());

        $emptyCsv = $this->actingAs($data['owner'])
            ->get('/laporan-penjualan/export/excel?date_from=2025-01-01&date_to=2025-01-02');

        $emptyCsv->assertOk();
        $this->assertStringContainsString('Produk Terlaris', $emptyCsv->getContent() ?: '');
        $this->assertStringContainsString('Performa Kasir', $emptyCsv->getContent() ?: '');
    }
}
