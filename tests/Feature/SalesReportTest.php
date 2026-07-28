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

class SalesReportTest extends TestCase
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

    private function seedSalesDataset(): array
    {
        $outletA = $this->defaultOutlet();
        $outletB = $this->createOutlet('Outlet B');
        $owner = $this->ownerUser();
        $cashierA = $this->cashierUser($outletA, 'Cashier A');
        $cashierB = $this->cashierUser($outletB, 'Cashier B');

        $mango = $this->createProduct('SAL-001', 'Mangga', 10000);
        $apple = $this->createProduct('SAL-002', 'Apel', 15000);
        $orange = $this->createProduct('SAL-003', 'Jeruk', 20000);

        $oldTransaction = $this->createTransaction([
            'invoice_number' => 'TRX-SAL-001',
            'outlet_id' => $outletA->id,
            'user_id' => $cashierA->id,
            'subtotal' => 10000,
            'discount' => 1000,
            'total' => 9000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_at' => '2026-07-20 09:00:00',
        ]);
        $this->attachItem($oldTransaction, $mango, 1, 10000);

        $todayTransaction = $this->createTransaction([
            'invoice_number' => 'TRX-SAL-002',
            'outlet_id' => $outletA->id,
            'user_id' => $cashierA->id,
            'subtotal' => 30000,
            'discount' => 0,
            'total' => 30000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_at' => '2026-07-24 10:00:00',
        ]);
        $this->attachItem($todayTransaction, $apple, 2, 30000);

        $cancelledTransaction = $this->createTransaction([
            'invoice_number' => 'TRX-SAL-003',
            'outlet_id' => $outletA->id,
            'user_id' => $cashierA->id,
            'subtotal' => 50000,
            'discount' => 0,
            'total' => 50000,
            'payment_method' => 'cash',
            'status' => 'cancelled',
            'created_at' => '2026-07-24 12:00:00',
        ]);
        $this->attachItem($cancelledTransaction, $mango, 3, 50000);

        $outletBTransaction = $this->createTransaction([
            'invoice_number' => 'TRX-SAL-004',
            'outlet_id' => $outletB->id,
            'user_id' => $cashierB->id,
            'subtotal' => 20000,
            'discount' => 0,
            'total' => 20000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_at' => '2026-07-24 13:00:00',
        ]);
        $this->attachItem($outletBTransaction, $orange, 1, 20000);

        $outletBSecondTransaction = $this->createTransaction([
            'invoice_number' => 'TRX-SAL-005',
            'outlet_id' => $outletB->id,
            'user_id' => $cashierB->id,
            'subtotal' => 15000,
            'discount' => 0,
            'total' => 15000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_at' => '2026-07-24 15:00:00',
        ]);
        $this->attachItem($outletBSecondTransaction, $orange, 1.5, 15000);

        return compact('owner', 'cashierA', 'cashierB', 'outletA', 'outletB', 'mango', 'apple', 'orange');
    }

    public function test_cashier_can_open_report_and_see_own_outlet_data_only(): void
    {
        $data = $this->seedSalesDataset();

        $response = $this->actingAs($data['cashierA'])
            ->get('/laporan-penjualan');

        $response->assertOk()
            ->assertSee('Laporan Penjualan')
            ->assertSee('Toko Buah Teras Fatimah')
            ->assertSee('2 transaksi')
            ->assertSee('Rp 39.000')
            ->assertSee('Rp 1.000')
            ->assertSee('3,000 kg')
            ->assertSee('2 item terjual')
            ->assertSee('Rp 19.500')
            ->assertSeeInOrder(['Apel', 'Mangga'])
            ->assertSee('Cashier A')
            ->assertDontSee('Outlet B')
            ->assertDontSee('Jeruk')
            ->assertDontSee('TRX-SAL-003');
    }

    public function test_cashier_query_cannot_be_manipulated_for_other_outlet(): void
    {
        $data = $this->seedSalesDataset();

        $this->actingAs($data['cashierA'])
            ->get('/laporan-penjualan?outlet_id='.$data['outletB']->id.'&cashier_id='.$data['cashierB']->id)
            ->assertOk()
            ->assertSee('Toko Buah Teras Fatimah')
            ->assertDontSee('Outlet B')
            ->assertDontSee('Jeruk')
            ->assertDontSee('Cashier B');
    }

    public function test_date_filter_and_today_preset_work(): void
    {
        $data = $this->seedSalesDataset();

        $this->actingAs($data['cashierA'])
            ->get('/laporan-penjualan?date_from=2026-07-20&date_to=2026-07-20')
            ->assertOk()
            ->assertSee('1 transaksi')
            ->assertSee('Rp 9.000')
            ->assertSee('1,000 kg')
            ->assertSee('1 item terjual');

        Carbon::setTestNow(Carbon::parse('2026-07-24 08:00:00'));

        $this->actingAs($data['cashierA'])
            ->get('/laporan-penjualan?preset=today')
            ->assertOk()
            ->assertSee('1 transaksi')
            ->assertSee('Rp 30.000')
            ->assertSee('2,000 kg')
            ->assertSee('1 item terjual');

        Carbon::setTestNow();
    }

    public function test_only_completed_transactions_are_counted_and_owner_filters_work(): void
    {
        $data = $this->seedSalesDataset();

        $this->actingAs($data['owner'])
            ->get('/laporan-penjualan?outlet_id='.$data['outletB']->id.'&cashier_id='.$data['cashierB']->id)
            ->assertOk()
            ->assertSee('2 transaksi')
            ->assertSee('Rp 39.000')
            ->assertSee('3,000 kg')
            ->assertSee('2 item terjual')
            ->assertSee('Apel')
            ->assertSee('Mangga')
            ->assertDontSee('Jeruk')
            ->assertDontSee('Cashier B');
    }

    public function test_product_and_cashier_reports_and_empty_state_are_rendered(): void
    {
        $data = $this->seedSalesDataset();

        $this->actingAs($data['owner'])
            ->get('/laporan-penjualan')
            ->assertOk()
            ->assertSeeInOrder(['Apel', 'Mangga'])
            ->assertSee('Cashier A')
            ->assertDontSee('Cashier B')
            ->assertSee('Toko Buah Teras Fatimah')
            ->assertDontSee('Outlet B');

        $this->actingAs($data['owner'])
            ->get('/laporan-penjualan?date_from=2025-01-01&date_to=2025-01-02')
            ->assertOk()
            ->assertSee('Belum ada transaksi completed untuk filter yang dipilih.')
            ->assertSee('Belum ada data tren penjualan.')
            ->assertSee('Belum ada produk terjual.')
            ->assertSee('Belum ada performa kasir untuk ditampilkan.');
    }
}
