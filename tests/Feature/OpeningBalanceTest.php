<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\AccountingSetting;
use App\Models\JournalEntry;
use App\Models\Outlet;
use App\Models\User;
use App\Services\OpeningBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OpeningBalanceTest extends TestCase
{
    use RefreshDatabase;

    private function outlet(string $name = Outlet::DEFAULT_NAME): Outlet
    {
        return $name === Outlet::DEFAULT_NAME
            ? Outlet::firstOrCreate(['name' => $name], ['address' => null, 'phone' => null])
            : Outlet::create(['name' => $name, 'address' => null, 'phone' => null]);
    }

    private function owner(): User
    {
        return User::factory()->create([
            'name' => 'Owner',
            'username' => 'owner-opening',
            'email' => 'owner-opening@example.test',
            'role' => 'owner',
            'outlet_id' => $this->outlet()->id,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
    }

    private function cashier(): User
    {
        return User::factory()->create([
            'name' => 'Cashier',
            'username' => 'cashier-opening',
            'email' => 'cashier-opening@example.test',
            'role' => 'cashier',
            'outlet_id' => $this->outlet()->id,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
    }

    private function accounts(bool $includeFixedAsset = true): void
    {
        $accounts = [
            ['code' => '1000', 'name' => 'Kas Tunai', 'type' => 'asset', 'subtype' => 'cash'],
            ['code' => '1010', 'name' => 'Bank/QRIS', 'type' => 'asset', 'subtype' => 'bank'],
            ['code' => '1100', 'name' => 'Persediaan', 'type' => 'asset', 'subtype' => 'inventory'],
            ['code' => '2000', 'name' => 'Utang Supplier', 'type' => 'liability', 'subtype' => 'payable'],
            ['code' => '3000', 'name' => 'Modal Pemilik', 'type' => 'equity', 'subtype' => 'capital'],
        ];
        if ($includeFixedAsset) {
            $accounts[] = ['code' => '1200', 'name' => 'Aset Tetap', 'type' => 'asset', 'subtype' => 'fixed_asset'];
        }
        foreach ($accounts as $account) {
            AccountingAccount::create($account + ['is_active' => true]);
        }
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'cutover_date' => '2026-12-01',
            'cash_balance' => '1000000.10',
            'bank_balance' => '500000.00',
            'inventory_balance' => '2500000.00',
            'fixed_asset_balance' => '1000000.00',
            'liabilities_balance' => '500000.00',
            'capital_balance' => '4500000.10',
        ], $overrides);
    }

    public function test_owner_can_open_page_and_cashier_gets_forbidden(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $this->get(route('accounting.opening-balance.create'))->assertOk();

        auth()->logout();
        $this->actingAs($this->cashier())->get(route('accounting.opening-balance.create'))->assertForbidden();
    }

    public function test_balanced_opening_balance_is_posted_once_with_precise_amounts(): void
    {
        $owner = $this->owner();
        $this->accounts();

        $this->actingAs($owner)->post(route('accounting.opening-balance.store'), $this->payload())->assertRedirect();
        $this->assertDatabaseHas('accounting_settings', ['outlet_id' => $owner->outlet_id]);
        $this->assertDatabaseHas('journal_entries', ['outlet_id' => $owner->outlet_id, 'journal_type' => 'opening_balance']);
        $this->assertDatabaseHas('journal_lines', ['debit' => '1000000.10']);
    }

    public function test_unbalanced_opening_balance_is_rejected_without_setting(): void
    {
        $owner = $this->owner();
        $this->accounts();

        $this->actingAs($owner)->post(route('accounting.opening-balance.store'), $this->payload(['capital_balance' => '1.00']))
            ->assertSessionHasErrors('balances');
        $this->assertDatabaseCount('accounting_settings', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_duplicate_opening_balance_is_rejected(): void
    {
        $owner = $this->owner();
        $this->accounts();
        $this->actingAs($owner)->post(route('accounting.opening-balance.store'), $this->payload());
        $this->actingAs($owner)->post(route('accounting.opening-balance.store'), $this->payload())
            ->assertSessionHasErrors('opening_balance');
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_cutover_is_required_and_other_outlet_is_forbidden(): void
    {
        $owner = $this->owner();
        $this->accounts();
        $this->actingAs($owner)->post(route('accounting.opening-balance.store'), $this->payload(['cutover_date' => '']))
            ->assertSessionHasErrors('cutover_date');

        $other = $this->outlet('Outlet Lain');
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(OpeningBalanceService::class)->post($owner, $other, '2026-12-01', $this->payload());
    }

    public function test_posting_failure_rolls_back_setting_and_journal(): void
    {
        $owner = $this->owner();
        $this->accounts(false);
        $payload = $this->payload(['fixed_asset_balance' => '100.00', 'capital_balance' => '4600.10']);

        $this->actingAs($owner)->post(route('accounting.opening-balance.store'), $payload)
            ->assertSessionHasErrors('balances');
        $this->assertDatabaseCount('accounting_settings', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_existing_transaction_tables_remain_unchanged(): void
    {
        $owner = $this->owner();
        $this->accounts();
        $this->actingAs($owner)->post(route('accounting.opening-balance.store'), $this->payload());
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('transaction_items', 0);
        $this->assertDatabaseCount('stock_purchases', 0);
        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseCount('assets', 0);
        $this->assertDatabaseCount('stock_histories', 0);
    }
}
