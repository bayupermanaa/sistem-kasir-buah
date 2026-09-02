<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Asset;
use App\Models\Expense;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FinancialReportTest extends TestCase
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

    private function createProduct(string $code, string $name, float $purchasePrice, float $sellingPrice): Product
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
            'purchase_price' => $purchasePrice,
            'selling_price' => $sellingPrice,
            'is_active' => true,
        ]);
    }

    private function ownerUser(): User
    {
        return User::factory()->create([
            'name' => 'Owner',
            'username' => 'owner-finance',
            'email' => 'owner-finance@terasfatimah.test',
            'role' => 'owner',
            'outlet_id' => $this->defaultOutlet()->id,
            'is_active' => true,
        ]);
    }

    private function cashierUser(Outlet $outlet, string $suffix): User
    {
        return User::factory()->create([
            'name' => 'Cashier',
            'username' => 'cashier-finance-'.$suffix,
            'email' => 'cashier-finance-'.$suffix.'@terasfatimah.test',
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

    private function createAsset(Outlet $outlet, User $creator): Asset
    {
        return Asset::create([
            'outlet_id' => $outlet->id,
            'asset_code' => 'AST-FIN-001',
            'name' => 'Kulkas Display',
            'category' => 'Elektronik',
            'purchase_date' => '2026-07-24',
            'purchase_price' => 5000000,
            'quantity' => 1,
            'total_cost' => 5000000,
            'payment_method' => 'cash',
            'useful_life_years' => 5,
            'residual_value' => 500000,
            'status' => 'Aktif',
            'notes' => 'Aset utama',
            'created_by' => $creator->id,
        ]);
    }

    private function seedFinancialDataset(): array
    {
        $outletA = $this->defaultOutlet();
        $outletB = $this->createOutlet('Outlet B');
        $owner = $this->ownerUser();
        $cashierA = $this->cashierUser($outletA, 'a');
        $cashierB = $this->cashierUser($outletB, 'b');

        $mango = $this->createProduct('FIN-001', 'Mangga', 7000, 10000);
        $apple = $this->createProduct('FIN-002', 'Apel', 10000, 15000);
        $orange = $this->createProduct('FIN-003', 'Jeruk', 15000, 20000);

        $cashTransaction = $this->createTransaction([
            'invoice_number' => 'TRX-FIN-001',
            'outlet_id' => $outletA->id,
            'user_id' => $cashierA->id,
            'subtotal' => 25000,
            'discount' => 1000,
            'total' => 24000,
            'payment_method' => 'cash',
            'cash_received' => 25000,
            'change_amount' => 1000,
            'status' => 'completed',
            'created_at' => '2026-07-24 10:00:00',
        ]);
        $this->attachItem($cashTransaction, $mango, 1, 10000);
        $this->attachItem($cashTransaction, $apple, 1, 15000);

        $qrisTransaction = $this->createTransaction([
            'invoice_number' => 'TRX-FIN-002',
            'outlet_id' => $outletA->id,
            'user_id' => $cashierA->id,
            'subtotal' => 15000,
            'discount' => 0,
            'total' => 15000,
            'payment_method' => 'qris',
            'status' => 'completed',
            'created_at' => '2026-07-24 11:00:00',
        ]);
        $this->attachItem($qrisTransaction, $orange, 1, 15000);

        $cancelledTransaction = $this->createTransaction([
            'invoice_number' => 'TRX-FIN-003',
            'outlet_id' => $outletA->id,
            'user_id' => $cashierA->id,
            'subtotal' => 50000,
            'discount' => 0,
            'total' => 50000,
            'payment_method' => 'cash',
            'status' => 'cancelled',
            'created_at' => '2026-07-24 12:00:00',
        ]);
        $this->attachItem($cancelledTransaction, $mango, 2, 20000);

        Expense::create([
            'outlet_id' => $outletA->id,
            'expense_date' => '2026-07-24',
            'category' => 'Operasional',
            'description' => 'Biaya listrik',
            'amount' => 3000,
            'notes' => null,
            'created_by' => $owner->id,
        ]);

        Expense::create([
            'outlet_id' => $outletA->id,
            'expense_date' => '2026-07-24',
            'category' => 'Transportasi',
            'description' => 'Ongkos kirim',
            'amount' => 2000,
            'notes' => null,
            'created_by' => $owner->id,
        ]);

        Expense::create([
            'outlet_id' => $outletB->id,
            'expense_date' => '2026-07-24',
            'category' => 'Operasional',
            'description' => 'Biaya outlet lain',
            'amount' => 9000,
            'notes' => null,
            'created_by' => $cashierB->id,
        ]);

        $this->createAsset($outletA, $owner);

        return compact('owner', 'cashierA', 'cashierB', 'outletA', 'outletB');
    }
    public function test_owner_can_see_profit_loss_cash_flow_and_neraca_placeholder(): void
    {
        $data = $this->seedFinancialDataset();
        $query = ['date_from' => '2026-07-24', 'date_to' => '2026-07-24'];

        $this->actingAs($data['owner'])->get(route('reports.sales', $query))
            ->assertOk()
            ->assertSee('Ringkasan Penjualan')
            ->assertDontSee('Laporan Laba Rugi');

        $this->actingAs($data['owner'])->get(route('reports.profit-loss', $query))
            ->assertOk()
            ->assertSee('Laporan Laba Rugi')
            ->assertSee('LABA BERSIH');

        $this->actingAs($data['owner'])->get(route('reports.cash-flow', $query))
            ->assertOk()
            ->assertSee('Laporan Arus Kas')
            ->assertSee('Pembelian Aset');

        $this->actingAs($data['owner'])->get(route('reports.balance-sheet', $query))
            ->assertOk()
            ->assertSee('Neraca')
            ->assertSee('Data modal dan ekuitas belum tersedia.');
    }
    public function test_cashier_only_sees_sales_report_and_financial_sections_are_hidden(): void
    {
        $data = $this->seedFinancialDataset();

        $this->actingAs($data['cashierA'])
            ->get('/laporan-penjualan?date_from=2026-07-24&date_to=2026-07-24')
            ->assertOk()
            ->assertSee('Ringkasan Penjualan')
            ->assertDontSee('Laporan Laba Rugi')
            ->assertDontSee('Arus Kas')
            ->assertDontSee('Neraca');
    }

    public function test_owner_can_open_report_menu(): void
    {
        $owner = $this->ownerUser();

        $this->actingAs($owner)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Pusat Laporan')
            ->assertSee('Laporan Laba Rugi')
            ->assertSee(route('reports.sales'), false);
    }

    public function test_cashier_can_open_report_menu_but_not_sensitive_financial_pages(): void
    {
        $outlet = $this->defaultOutlet();
        $cashier = $this->cashierUser($outlet, 'menu');

        $this->actingAs($cashier)->get(route('reports.index'))->assertOk();
        $this->actingAs($cashier)->get(route('reports.profit-loss'))->assertForbidden();
        $this->actingAs($cashier)->get(route('reports.cash-flow'))->assertForbidden();
        $this->actingAs($cashier)->get(route('reports.balance-sheet'))->assertForbidden();
    }

    public function test_operating_expense_report_only_shows_operational_expenses(): void
    {
        $data = $this->seedFinancialDataset();

        $this->actingAs($data['owner'])
            ->get(route('reports.expenses', ['date_from' => '2026-07-24', 'date_to' => '2026-07-24']))
            ->assertOk()
            ->assertSee('Biaya listrik')
            ->assertDontSee('Kulkas Display')
            ->assertDontSee('Pembelian Stok');
    }
}
