<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Outlet;
use App\Models\OutletProductStock;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierPayable;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_empty_dashboard_without_fake_statistics(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Rp 0')
            ->assertSee('Belum ada transaksi selesai.')
            ->assertSee('Semua stok berada dalam kondisi aman.')
            ->assertSee('Accounting belum aktif.');
    }

    public function test_cashier_cannot_open_owner_dashboard(): void
    {
        $cashier = $this->cashier();

        $this->actingAs($cashier)
            ->get('/dashboard')
            ->assertForbidden();
    }

    public function test_dashboard_aggregates_today_sales_stock_warning_and_outstanding_payable(): void
    {
        $owner = $this->owner();
        $outlet = Outlet::default();
        [$product, $stock] = $this->productWithStock($outlet, 'Apel Dashboard', 2, 5);
        $supplier = Supplier::create(['name' => 'Supplier Dashboard']);

        $todayTransaction = Transaction::create([
            'invoice_number' => 'TRX-DASH-001',
            'outlet_id' => $outlet->id,
            'user_id' => $owner->id,
            'subtotal' => 25000,
            'discount' => 0,
            'total' => 25000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);
        $todayTransaction->forceFill([
            'created_at' => now(),
            'updated_at' => now(),
        ])->saveQuietly();
        $oldTransaction = Transaction::create([
            'invoice_number' => 'TRX-DASH-OLD',
            'outlet_id' => $outlet->id,
            'user_id' => $owner->id,
            'subtotal' => 90000,
            'discount' => 0,
            'total' => 90000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);
        $oldTransaction->forceFill([
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ])->saveQuietly();
        SupplierPayable::create([
            'outlet_id' => $outlet->id,
            'supplier_id' => $supplier->id,
            'debt_date' => now()->toDateString(),
            'original_amount' => 100000,
            'paid_amount' => 25000,
            'outstanding_amount' => 75000,
            'status' => 'partial',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Rp 25.000')
            ->assertSee('>1<', false)
            ->assertSee('Rp 75.000')
            ->assertSee('Apel Dashboard')
            ->assertSee('Menipis')
            ->assertSee('TRX-DASH-001')
            ->assertSee('TRX-DASH-OLD');

        $this->assertEquals(2, $stock->fresh()->quantity + 0);
    }

    public function test_dashboard_sales_trend_contains_seven_days_and_ignores_other_outlet(): void
    {
        $owner = $this->owner();
        $defaultOutlet = Outlet::default();
        $otherOutlet = Outlet::create(['name' => 'Outlet Lama']);

        $this->transaction($owner, $defaultOutlet, 'TRX-TREND-001', 10000, now()->subDays(6));
        $this->transaction($owner, $otherOutlet, 'TRX-OTHER-001', 90000, now());

        $this->actingAs($owner)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Tren Penjualan 7 Hari')
            ->assertSee(now()->subDays(6)->translatedFormat('D d/m'))
            ->assertSee(now()->translatedFormat('D d/m'))
            ->assertSee('Rp 10.000')
            ->assertDontSee('Rp 90.000');
    }

    private function owner(): User
    {
        $outlet = Outlet::firstOrCreate(['name' => Outlet::DEFAULT_NAME]);

        return User::factory()->create([
            'name' => 'Owner Dashboard',
            'username' => 'owner-dashboard-'.uniqid(),
            'email' => uniqid().'@dashboard.test',
            'role' => 'owner',
            'outlet_id' => $outlet->id,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
    }

    private function cashier(): User
    {
        $outlet = Outlet::firstOrCreate(['name' => Outlet::DEFAULT_NAME]);

        return User::factory()->create([
            'name' => 'Cashier Dashboard',
            'username' => 'cashier-dashboard-'.uniqid(),
            'email' => uniqid().'@dashboard.test',
            'role' => 'cashier',
            'outlet_id' => $outlet->id,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
    }

    private function productWithStock(Outlet $outlet, string $name, int $quantity, int $minimum): array
    {
        $category = Category::create(['name' => $name.' Category', 'slug' => strtolower(str_replace(' ', '-', $name)).'-category']);
        $unit = Unit::create(['name' => $name.' Unit', 'symbol' => 'u'.uniqid()]);
        $product = Product::create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'code' => 'DASH-'.uniqid(),
            'name' => $name,
            'purchase_price' => 1000,
            'selling_price' => 2000,
            'is_active' => true,
        ]);
        $stock = OutletProductStock::create([
            'outlet_id' => $outlet->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'minimum_stock' => $minimum,
        ]);

        return [$product, $stock];
    }

    private function transaction(User $user, Outlet $outlet, string $invoice, int $total, Carbon $createdAt): Transaction
    {
        $transaction = Transaction::create([
            'invoice_number' => $invoice,
            'outlet_id' => $outlet->id,
            'user_id' => $user->id,
            'subtotal' => $total,
            'discount' => 0,
            'total' => $total,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);
        return $transaction->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly() ? $transaction->fresh() : $transaction;
    }
}
