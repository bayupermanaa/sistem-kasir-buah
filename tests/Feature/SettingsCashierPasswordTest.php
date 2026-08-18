<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingsCashierPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function ownerUser(): User
    {
        return User::factory()->create([
            'name' => 'Owner',
            'username' => 'owner-settings',
            'email' => 'owner-settings@terasfatimah.test',
            'role' => 'owner',
            'outlet_id' => null,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
    }

    private function cashierUser(Outlet $outlet, string $suffix = '1'): User
    {
        return User::factory()->create([
            'name' => 'Cashier '.$suffix,
            'username' => 'cashier-settings-'.$suffix,
            'email' => 'cashier-settings-'.$suffix.'@terasfatimah.test',
            'role' => 'cashier',
            'outlet_id' => $outlet->id,
            'is_active' => $suffix !== '2',
            'password' => Hash::make('oldpassword'),
        ]);
    }

    public function test_owner_can_open_settings_and_see_cashier_accounts(): void
    {
        $owner = $this->ownerUser();
        $outlet = Outlet::create(['name' => 'Toko Buah Teras Fatimah']);
        $activeCashier = $this->cashierUser($outlet, '1');
        $inactiveCashier = $this->cashierUser($outlet, '2');

        $response = $this->actingAs($owner)->get('/pengaturan');

        $response->assertOk()
            ->assertSee('Manajemen Akun Kasir')
            ->assertSee($activeCashier->name)
            ->assertSee($activeCashier->username)
            ->assertSee('Aktif')
            ->assertSee($inactiveCashier->name)
            ->assertSee($inactiveCashier->username)
            ->assertSee('Nonaktif')
            ->assertSee('Ubah Password');
    }

    public function test_cashier_cannot_open_or_change_settings(): void
    {
        $outlet = Outlet::create(['name' => 'Toko Buah Teras Fatimah']);
        $cashier = $this->cashierUser($outlet);

        $this->actingAs($cashier)
            ->get('/pengaturan')
            ->assertForbidden();

        $targetCashier = $this->cashierUser($outlet, '3');

        $this->actingAs($cashier)
            ->put(route('pengaturan.cashiers.password.update', $targetCashier), [
                'cashier_id' => $targetCashier->id,
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])
            ->assertForbidden();
    }

    public function test_owner_can_change_cashier_password_and_login_uses_new_password(): void
    {
        $owner = $this->ownerUser();
        $outlet = Outlet::create(['name' => 'Toko Buah Teras Fatimah']);
        $cashier = $this->cashierUser($outlet);

        $response = $this->actingAs($owner)->put(route('pengaturan.cashiers.password.update', $cashier), [
            'cashier_id' => $cashier->id,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect('/pengaturan');
        $response->assertSessionHas('success', 'Password kasir berhasil diperbarui.');

        $cashier->refresh();

        $this->assertTrue(Hash::check('newpassword123', $cashier->password));
        $this->assertFalse(Hash::check('oldpassword', $cashier->password));

        $this->post('/logout');

        $this->post('/login', [
            'username' => $cashier->username,
            'password' => 'newpassword123',
        ])->assertRedirect('/kasir');

        $this->post('/logout');

        $this->post('/login', [
            'username' => $cashier->username,
            'password' => 'oldpassword',
        ])->assertSessionHasErrors('username');
    }

    public function test_password_confirmation_and_length_are_validated(): void
    {
        $owner = $this->ownerUser();
        $outlet = Outlet::create(['name' => 'Toko Buah Teras Fatimah']);
        $cashier = $this->cashierUser($outlet);

        $this->actingAs($owner)
            ->from('/pengaturan')
            ->put(route('pengaturan.cashiers.password.update', $cashier), [
                'cashier_id' => $cashier->id,
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertRedirect('/pengaturan')
            ->assertSessionHasErrors('password');

        $this->actingAs($owner)
            ->from('/pengaturan')
            ->put(route('pengaturan.cashiers.password.update', $cashier), [
                'cashier_id' => $cashier->id,
                'password' => 'newpassword123',
                'password_confirmation' => 'different123',
            ])
            ->assertRedirect('/pengaturan')
            ->assertSessionHasErrors('password');
    }

    public function test_target_non_cashier_is_rejected(): void
    {
        $owner = $this->ownerUser();
        $outlet = Outlet::create(['name' => 'Toko Buah Teras Fatimah']);
        $targetOwner = User::factory()->create([
            'name' => 'Owner Target',
            'username' => 'owner-target',
            'email' => 'owner-target@terasfatimah.test',
            'role' => 'owner',
            'outlet_id' => $outlet->id,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($owner)
            ->put(route('pengaturan.cashiers.password.update', $targetOwner), [
                'cashier_id' => $targetOwner->id,
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])
            ->assertForbidden();
    }
}
