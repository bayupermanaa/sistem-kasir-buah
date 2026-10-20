<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\Asset;
use App\Models\JournalEntry;
use App\Models\Outlet;
use App\Models\User;
use App\Services\AssetAccountingService;
use App\Services\OpeningBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AssetAccountingTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $outlet = Outlet::firstOrCreate(['name' => Outlet::DEFAULT_NAME]);

        return User::factory()->create([
            'name' => 'Owner Asset Accounting',
            'username' => 'owner-asset-accounting-'.Str::random(6),
            'email' => Str::random(8).'@example.test',
            'role' => 'owner',
            'outlet_id' => $outlet->id,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
    }

    private function activate(User $owner, bool $includeFixedAsset = true): void
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

        app(OpeningBalanceService::class)->post($owner, $owner->outlet, '2026-01-01', [
            'cash_balance' => '100000.00',
            'bank_balance' => '0.00',
            'inventory_balance' => '0.00',
            'fixed_asset_balance' => '0.00',
            'liabilities_balance' => '0.00',
            'capital_balance' => '100000.00',
        ]);
    }

    private function payload(string $method = 'cash'): array
    {
        return [
            'name' => 'Mesin Produksi',
            'category' => 'Mesin/Peralatan Produksi',
            'purchase_date' => '2026-01-02',
            'purchase_price' => '1000000.10',
            'quantity' => 1,
            'payment_method' => $method,
            'useful_life_years' => 5,
            'residual_value' => '0.00',
            'status' => 'Aktif',
            'notes' => 'Aset pembelian baru',
        ];
    }

    public function test_active_asset_purchases_support_cash_transfer_and_qris(): void
    {
        $owner = $this->owner();
        $this->activate($owner);

        foreach (['cash', 'transfer', 'qris'] as $method) {
            $this->actingAs($owner)->post(route('assets.store'), $this->payload($method))
                ->assertRedirect(route('assets.index'));
        }

        $this->assertDatabaseCount('assets', 3);
        $this->assertDatabaseCount('journal_entries', 4);
        $this->assertDatabaseCount('journal_lines', 8);
        $this->assertDatabaseHas('assets', ['total_cost' => '1000000.10', 'payment_method' => 'cash']);

        $journals = JournalEntry::query()->where('journal_type', 'asset_purchase')->with('lines')->get();
        $this->assertCount(3, $journals);
        foreach ($journals as $journal) {
            $this->assertSame('1000000.10', number_format((float) $journal->lines->sum('debit'), 2, '.', ''));
            $this->assertSame('1000000.10', number_format((float) $journal->lines->sum('credit'), 2, '.', ''));
        }
    }

    public function test_asset_journal_is_idempotent_and_journal_failure_rolls_back_asset(): void
    {
        $owner = $this->owner();
        $this->activate($owner);

        $this->actingAs($owner)->post(route('assets.store'), $this->payload())->assertRedirect();
        $asset = Asset::firstOrFail();

        $this->expectException(ValidationException::class);
        app(AssetAccountingService::class)->recordAsset($asset->load('creator'));

        $this->assertDatabaseCount('assets', 1);

    }

    public function test_missing_asset_account_rolls_back_asset_creation(): void
    {
        $owner = $this->owner();
        $this->activate($owner, false);

        $this->actingAs($owner)->post(route('assets.store'), $this->payload())
            ->assertSessionHasErrors('accounting');

        $this->assertDatabaseCount('assets', 0);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_asset_before_cutover_is_preserved_without_historical_journal(): void
    {
        $owner = $this->owner();
        $this->activate($owner);
        $payload = $this->payload();
        $payload['purchase_date'] = '2025-12-31';

        $this->actingAs($owner)->post(route('assets.store'), $payload)->assertRedirect();

        $this->assertDatabaseCount('assets', 1);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseMissing('journal_entries', ['journal_type' => 'asset_purchase']);
    }

    public function test_journaled_asset_cannot_be_edited_or_deleted(): void
    {
        $owner = $this->owner();
        $this->activate($owner);
        $this->actingAs($owner)->post(route('assets.store'), $this->payload())->assertRedirect();
        $asset = Asset::firstOrFail();

        $this->actingAs($owner)->put(route('assets.update', $asset), $this->payload())
            ->assertStatus(422);
        $this->actingAs($owner)->delete(route('assets.destroy', $asset))
            ->assertStatus(422);

        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'name' => 'Mesin Produksi']);
        $this->assertDatabaseCount('journal_entries', 2);
    }

    public function test_unjournaled_historical_asset_allows_safe_edit_but_blocks_financial_changes(): void
    {
        $owner = $this->owner();
        $this->activate($owner);
        $payload = $this->payload();
        $payload['purchase_date'] = '2025-12-31';

        $this->actingAs($owner)->post(route('assets.store'), $payload)->assertRedirect();
        $asset = Asset::firstOrFail();

        $safeUpdate = $payload;
        $safeUpdate['name'] = 'Mesin Produksi Lama';
        $this->actingAs($owner)->put(route('assets.update', $asset), $safeUpdate)
            ->assertRedirect(route('assets.index'));
        $this->assertSame('Mesin Produksi Lama', $asset->refresh()->name);
        $this->assertSame('2025-12-31', $asset->purchase_date->toDateString());

        $financialUpdate = $safeUpdate;
        $financialUpdate['purchase_date'] = '2026-01-02';
        $financialUpdate['purchase_price'] = '2000000.00';
        $financialUpdate['payment_method'] = 'transfer';
        $this->actingAs($owner)->put(route('assets.update', $asset), $financialUpdate)
            ->assertStatus(422);

        $asset->refresh();
        $this->assertSame('2025-12-31', $asset->purchase_date->toDateString());
        $this->assertSame('1000000.10', $asset->purchase_price);
        $this->assertSame('cash', $asset->payment_method);
        $this->assertDatabaseMissing('journal_entries', ['journal_type' => 'asset_purchase']);
    }
}
