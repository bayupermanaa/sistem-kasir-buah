<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExpenseCrudTest extends TestCase
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
            'username' => 'owner-expense-'.$suffix,
            'email' => 'owner-expense-'.$suffix.'@terasfatimah.test',
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
            'username' => 'cashier-expense-'.$suffix,
            'email' => 'cashier-expense-'.$suffix.'@terasfatimah.test',
            'role' => 'cashier',
            'outlet_id' => $this->defaultOutlet()->id,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
    }

    private function createExpense(array $overrides = []): Expense
    {
        return Expense::create(array_merge([
            'outlet_id' => $this->defaultOutlet()->id,
            'expense_date' => '2026-08-06',
            'category' => 'Operasional',
            'description' => 'Biaya operasional harian',
            'amount' => 25000,
            'notes' => null,
            'created_by' => $this->ownerUser()->id,
        ], $overrides));
    }

    public function test_owner_can_open_expense_page(): void
    {
        $owner = $this->ownerUser();

        $this->actingAs($owner)
            ->get('/pengeluaran')
            ->assertOk()
            ->assertSee('Pengeluaran')
            ->assertSee('Tambah Pengeluaran');
    }

    public function test_owner_can_add_expense_and_store_to_database(): void
    {
        $owner = $this->ownerUser();

        $this->actingAs($owner)
            ->post('/pengeluaran', [
                'expense_date' => '2026-08-06',
                'category' => 'Listrik',
                'description' => 'Tagihan listrik toko',
                'amount' => 150000,
                'notes' => 'Periode bulan ini',
            ])
            ->assertRedirect(route('expenses.index'))
            ->assertSessionHas('success', 'Pengeluaran berhasil ditambahkan.');

        $this->assertDatabaseHas('expenses', [
            'outlet_id' => $this->defaultOutlet()->id,
            'category' => 'Listrik',
            'description' => 'Tagihan listrik toko',
            'amount' => '150000.00',
        ]);
    }

    public function test_expense_amount_must_be_greater_than_zero(): void
    {
        $owner = $this->ownerUser();

        $this->actingAs($owner)
            ->post('/pengeluaran', [
                'expense_date' => '2026-08-06',
                'category' => 'Air',
                'description' => 'Tagihan air',
                'amount' => 0,
                'notes' => null,
            ])
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_owner_can_edit_expense(): void
    {
        $owner = $this->ownerUser();
        $expense = $this->createExpense();

        $this->actingAs($owner)
            ->put(route('expenses.update', $expense), [
                'expense_date' => '2026-08-07',
                'category' => 'Transportasi',
                'description' => 'Ongkos kirim baru',
                'amount' => 45000,
                'notes' => 'Diupdate',
            ])
            ->assertRedirect(route('expenses.index'))
            ->assertSessionHas('success', 'Pengeluaran berhasil diperbarui.');

        $expense->refresh();
        $this->assertSame('2026-08-07', $expense->expense_date->format('Y-m-d'));
        $this->assertSame('Transportasi', $expense->category);
        $this->assertSame('Ongkos kirim baru', $expense->description);
        $this->assertSame('45000.00', $expense->amount);
    }

    public function test_owner_can_delete_expense(): void
    {
        $owner = $this->ownerUser();
        $expense = $this->createExpense();

        $this->actingAs($owner)
            ->delete(route('expenses.destroy', $expense))
            ->assertRedirect(route('expenses.index'))
            ->assertSessionHas('success', 'Pengeluaran berhasil dihapus.');

        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    public function test_cashier_can_open_expense_page_but_cannot_edit_or_delete(): void
    {
        $cashier = $this->cashierUser();
        $expense = $this->createExpense();

        $this->actingAs($cashier)
            ->get('/pengeluaran')
            ->assertOk()
            ->assertSee('Pengeluaran')
            ->assertDontSee('Edit Pengeluaran')
            ->assertDontSee('Hapus Pengeluaran');

        $this->actingAs($cashier)
            ->get(route('expenses.edit', $expense))
            ->assertForbidden();

        $this->actingAs($cashier)
            ->delete(route('expenses.destroy', $expense))
            ->assertForbidden();
    }

    public function test_expense_list_isolated_to_default_outlet(): void
    {
        $owner = $this->ownerUser();
        $otherOutlet = $this->otherOutlet();

        $visibleExpense = $this->createExpense([
            'description' => 'Biaya terlihat',
        ]);

        Expense::create([
            'outlet_id' => $otherOutlet->id,
            'expense_date' => '2026-08-06',
            'category' => 'Operasional',
            'description' => 'Biaya outlet lain',
            'amount' => 90000,
            'notes' => null,
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get('/pengeluaran')
            ->assertOk()
            ->assertSee('Biaya terlihat')
            ->assertDontSee('Biaya outlet lain');
    }
}
