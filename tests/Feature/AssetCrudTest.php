<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AssetCrudTest extends TestCase
{
    use RefreshDatabase;

    private function defaultOutlet(): Outlet
    {
        return Outlet::firstOrCreate([
            'name' => Outlet::DEFAULT_NAME,
        ], [
            'address' => null,
            'phone' => null,
        ]);
    }

    private function otherOutlet(): Outlet
    {
        return Outlet::create([
            'name' => 'Outlet Lain',
            'address' => null,
            'phone' => null,
        ]);
    }

    private function ownerUser(): User
    {
        $suffix = str()->random(5);

        return User::factory()->create([
            'name' => 'Owner',
            'username' => 'owner-asset-'.$suffix,
            'email' => 'owner-asset-'.$suffix.'@terasfatimah.test',
            'role' => 'owner',
            'outlet_id' => $this->defaultOutlet()->id,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
    }

    private function cashierUser(): User
    {
        $suffix = str()->random(5);

        return User::factory()->create([
            'name' => 'Cashier',
            'username' => 'cashier-asset-'.$suffix,
            'email' => 'cashier-asset-'.$suffix.'@terasfatimah.test',
            'role' => 'cashier',
            'outlet_id' => $this->defaultOutlet()->id,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
    }

    private function createAsset(array $overrides = []): Asset
    {
        return Asset::create(array_merge([
            'outlet_id' => $this->defaultOutlet()->id,
            'asset_code' => 'AST-0001',
            'name' => 'Laptop Kasir',
            'category' => 'Elektronik',
            'purchase_date' => '2026-08-22',
            'purchase_price' => 5000000,
            'quantity' => 1,
            'total_cost' => 5000000,
            'payment_method' => 'cash',
            'useful_life_years' => 4,
            'residual_value' => 500000,
            'status' => 'Aktif',
            'notes' => 'Unit utama',
            'created_by' => $this->ownerUser()->id,
        ], $overrides));
    }

    public function test_owner_can_open_asset_page_and_cashier_is_forbidden(): void
    {
        $owner = $this->ownerUser();
        $cashier = $this->cashierUser();

        $this->actingAs($owner)
            ->get(route('assets.index'))
            ->assertOk()
            ->assertSee('Aset')
            ->assertSee('Tambah Aset');

        $this->actingAs($cashier)
            ->get(route('assets.index'))
            ->assertForbidden();
    }

    public function test_owner_can_create_asset_with_server_side_total_cost_and_auto_code(): void
    {
        $owner = $this->ownerUser();

        $this->actingAs($owner)
            ->post(route('assets.store'), [
                'name' => 'Kulkas Display',
                'category' => 'Elektronik',
                'purchase_date' => '2026-08-22',
                'purchase_price' => 3500000,
                'quantity' => 2,
                'payment_method' => 'qris',
                'useful_life_years' => 5,
                'residual_value' => 250000,
                'status' => 'Aktif',
                'notes' => 'Aset baru',
            ])
            ->assertRedirect(route('assets.index'))
            ->assertSessionHas('success', 'Aset berhasil ditambahkan.');

        $asset = Asset::query()->firstOrFail();

        $this->assertMatchesRegularExpression('/^AST-\d{4}$/', $asset->asset_code);
        $this->assertSame('7000000.00', $asset->total_cost);
        $this->assertSame('3500000.00', $asset->purchase_price);
        $this->assertSame(2, $asset->quantity);

        $this->actingAs($owner)
            ->post(route('assets.store'), [
                'name' => 'Rak Besi',
                'category' => 'Furniture',
                'purchase_date' => '2026-08-22',
                'purchase_price' => 1200000,
                'quantity' => 1,
                'payment_method' => 'cash',
                'status' => 'Aktif',
            ])
            ->assertRedirect(route('assets.index'));

        $secondAsset = Asset::query()->where('name', 'Rak Besi')->firstOrFail();
        $this->assertNotSame($asset->asset_code, $secondAsset->asset_code);
        $this->assertSame('AST-0002', $secondAsset->asset_code);
    }

    public function test_owner_can_update_asset_without_changing_code(): void
    {
        $owner = $this->ownerUser();
        $asset = $this->createAsset();
        $originalCode = $asset->asset_code;

        $this->actingAs($owner)
            ->put(route('assets.update', $asset), [
                'name' => 'Laptop Kasir Baru',
                'category' => 'Elektronik',
                'purchase_date' => '2026-08-23',
                'purchase_price' => 6000000,
                'quantity' => 2,
                'payment_method' => 'cash',
                'useful_life_years' => 3,
                'residual_value' => 1000000,
                'status' => 'Aktif',
                'notes' => 'Diperbarui',
            ])
            ->assertRedirect(route('assets.index'))
            ->assertSessionHas('success', 'Aset berhasil diperbarui.');

        $asset->refresh();
        $this->assertSame($originalCode, $asset->asset_code);
        $this->assertSame('Laptop Kasir Baru', $asset->name);
        $this->assertSame('12000000.00', $asset->total_cost);
    }

    public function test_owner_can_delete_asset_using_soft_delete(): void
    {
        $owner = $this->ownerUser();
        $asset = $this->createAsset();

        $this->actingAs($owner)
            ->delete(route('assets.destroy', $asset))
            ->assertRedirect(route('assets.index'))
            ->assertSessionHas('success', 'Aset berhasil dihapus.');

        $this->assertSoftDeleted('assets', ['id' => $asset->id]);
    }

    public function test_filters_work_and_other_outlet_data_is_hidden(): void
    {
        $owner = $this->ownerUser();
        $otherOutlet = $this->otherOutlet();

        $visibleAsset = $this->createAsset([
            'asset_code' => 'AST-0100',
            'name' => 'Mesin Blender',
            'category' => 'Mesin/Peralatan Produksi',
            'status' => 'Aktif',
        ]);

        Asset::create([
            'outlet_id' => $otherOutlet->id,
            'asset_code' => 'AST-9999',
            'name' => 'Aset Outlet Lain',
            'category' => 'Lainnya',
            'purchase_date' => '2026-08-22',
            'purchase_price' => 1000000,
            'quantity' => 1,
            'total_cost' => 1000000,
            'payment_method' => 'cash',
            'useful_life_years' => null,
            'residual_value' => 0,
            'status' => 'Aktif',
            'notes' => null,
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('assets.index', ['category' => 'Mesin/Peralatan Produksi', 'status' => 'Aktif']))
            ->assertOk()
            ->assertSee('Mesin Blender')
            ->assertDontSee('Aset Outlet Lain');

        $this->assertSame('Mesin/Peralatan Produksi', $visibleAsset->category);
    }
}
