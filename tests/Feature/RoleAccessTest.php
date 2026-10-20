<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_redirected_to_login_when_opening_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_owner_can_access_all_main_routes(): void
    {
        $owner = User::factory()->create([
            'name' => 'Owner',
            'username' => 'owner',
            'email' => 'owner@terasfatimah.test',
            'role' => 'owner',
            'outlet_id' => null,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($owner)
            ->get('/dashboard')
            ->assertOk();

        $this->actingAs($owner)
            ->get('/kasir')
            ->assertOk();

        $this->actingAs($owner)
            ->get('/pengaturan')
            ->assertOk();

        $this->actingAs($owner)
            ->get('/stock-opname')
            ->assertOk()
            ->assertSee('Stock Opname')
            ->assertSee('bi-clipboard-check');
    }

    public function test_cashier_can_access_kasir_but_not_owner_pages(): void
    {
        $outlet = Outlet::create([
            'name' => 'Toko Buah Teras Fatimah',
        ]);

        $cashier = User::factory()->create([
            'name' => 'Cashier',
            'username' => 'cashier',
            'email' => 'cashier@terasfatimah.test',
            'role' => 'cashier',
            'outlet_id' => $outlet->id,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($cashier)
            ->get('/kasir')
            ->assertOk();

        $this->actingAs($cashier)
            ->get('/dashboard')
            ->assertForbidden();

        $this->actingAs($cashier)
            ->get('/pengaturan')
            ->assertForbidden();

        $this->actingAs($cashier)
            ->get('/kasir')
            ->assertOk()
            ->assertDontSee('Stock Opname');
    }

    public function test_login_redirects_based_on_role(): void
    {
        User::factory()->create([
            'name' => 'Owner',
            'username' => 'owner',
            'email' => 'owner@terasfatimah.test',
            'role' => 'owner',
            'outlet_id' => null,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $this->post('/login', [
            'username' => 'owner',
            'password' => 'password',
        ])->assertRedirect('/dashboard');
    }

    public function test_cashier_login_redirects_to_kasir(): void
    {
        $outlet = Outlet::create([
            'name' => 'Toko Buah Teras Fatimah',
        ]);

        User::factory()->create([
            'name' => 'Cashier',
            'username' => 'cashier',
            'email' => 'cashier@terasfatimah.test',
            'role' => 'cashier',
            'outlet_id' => $outlet->id,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $this->post('/login', [
            'username' => 'cashier',
            'password' => 'password',
        ])->assertRedirect('/kasir');
    }
}
