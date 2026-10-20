<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\Outlet;
use App\Models\Supplier;
use App\Models\SupplierPayable;
use App\Models\User;
use App\Services\OpeningBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SupplierPayableTest extends TestCase
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
        return User::factory()->create(['name' => 'Owner', 'username' => 'owner-payable-'.Str::random(6), 'email' => Str::random(8).'@example.test', 'role' => 'owner', 'outlet_id' => $this->outlet()->id, 'is_active' => true, 'password' => Hash::make('password')]);
    }

    private function cashier(): User
    {
        return User::factory()->create(['name' => 'Cashier', 'username' => 'cashier-payable-'.Str::random(6), 'email' => Str::random(8).'@example.test', 'role' => 'cashier', 'outlet_id' => $this->outlet()->id, 'is_active' => true, 'password' => Hash::make('password')]);
    }

    private function supplier(): Supplier
    {
        return Supplier::create(['name' => 'Supplier Buah Segar', 'phone' => '08123456789', 'address' => 'Pasar Buah', 'is_active' => true]);
    }

    private function payable(User $owner, Supplier $supplier, string $amount = '1000.00'): SupplierPayable
    {
        return app(\App\Services\SupplierPayableService::class)->createPayable($owner, $this->outlet(), [
            'supplier_id' => $supplier->id, 'debt_date' => '2026-12-01', 'due_date' => '2026-12-31',
            'original_amount' => $amount, 'invoice_number' => 'INV-001', 'description' => 'Pembelian buah kredit',
        ]);
    }

    private function accounts(bool $bank = true): void
    {
        $accounts = [
            ['code' => '1000', 'name' => 'Kas Tunai', 'type' => 'asset', 'subtype' => 'cash'],
            ['code' => '2000', 'name' => 'Utang Supplier', 'type' => 'liability', 'subtype' => 'payable'],
            ['code' => '3000', 'name' => 'Modal Pemilik', 'type' => 'equity', 'subtype' => 'capital'],
        ];
        if ($bank) $accounts[] = ['code' => '1010', 'name' => 'Bank/QRIS', 'type' => 'asset', 'subtype' => 'bank'];
        foreach ($accounts as $account) AccountingAccount::create($account + ['is_active' => true]);
    }

    public function test_owner_can_manage_supplier_and_cashier_is_forbidden(): void
    {
        $owner = $this->owner();
        $response = $this->actingAs($owner)->post(route('suppliers.store'), ['name' => 'Supplier Baru', 'is_active' => 1]);
        $response->assertRedirect(route('suppliers.index'));
        $supplier = Supplier::where('name', 'Supplier Baru')->firstOrFail();
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'is_active' => 1]);
        $this->actingAs($owner)->put(route('suppliers.update', $supplier), ['name' => 'Supplier Diperbarui', 'is_active' => 1])->assertRedirect();
        $this->actingAs($owner)->delete(route('suppliers.destroy', $supplier))->assertRedirect();
        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
        $this->actingAs($this->cashier())->get(route('suppliers.index'))->assertForbidden();
        $this->actingAs($this->cashier())->get(route('payables.index'))->assertForbidden();
    }

    public function test_owner_can_create_payable_and_isolated_detail_is_rejected(): void
    {
        $owner = $this->owner(); $supplier = $this->supplier();
        $this->actingAs($owner)->post(route('payables.store'), ['supplier_id' => $supplier->id, 'debt_date' => '2026-12-01', 'original_amount' => '1250.50'])->assertRedirect();
        $payable = SupplierPayable::firstOrFail();
        $this->assertSame('open', $payable->status); $this->assertSame('1250.50', $payable->outstanding_amount);
        $other = $this->outlet('Toko Lain');
        $otherPayable = SupplierPayable::create(['outlet_id' => $other->id, 'supplier_id' => $supplier->id, 'debt_date' => '2026-12-01', 'original_amount' => '10.00', 'paid_amount' => '0.00', 'outstanding_amount' => '10.00', 'status' => 'open']);
        $this->actingAs($owner)->get(route('payables.show', $otherPayable))->assertNotFound();
    }

    public function test_partial_payment_and_full_settlement_work(): void
    {
        $owner = $this->owner(); $payable = $this->payable($owner, $this->supplier(), '1000.00');
        $this->actingAs($owner)->post(route('payables.payments.store', $payable), ['payment_date' => '2026-12-02', 'amount' => '400.10', 'payment_method' => 'cash', 'idempotency_key' => (string) Str::uuid()])->assertRedirect();
        $payable->refresh(); $this->assertSame('partial', $payable->status); $this->assertSame('599.90', $payable->outstanding_amount);
        $this->actingAs($owner)->post(route('payables.payments.store', $payable), ['payment_date' => '2026-12-03', 'amount' => '599.90', 'payment_method' => 'cash', 'idempotency_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertDatabaseHas('supplier_payables', ['id' => $payable->id, 'status' => 'paid', 'outstanding_amount' => '0.00']); $this->assertDatabaseCount('payable_payments', 2);
    }

    public function test_decimal_amount_round_trips_without_float_conversion(): void
    {
        $owner = $this->owner();
        $payable = $this->payable($owner, $this->supplier(), '1000000.10');

        $this->assertSame('1000000.10', $payable->fresh()->original_amount);
        $this->assertSame(100000010, app(\App\Services\SupplierPayableService::class)->totalsToMinorUnits($payable)['original']);

        $this->actingAs($owner)->post(route('payables.payments.store', $payable), [
            'payment_date' => '2026-12-02',
            'amount' => '0.10',
            'payment_method' => 'cash',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $payable->refresh();
        $this->assertSame('0.10', $payable->paid_amount);
        $this->assertSame('1000000.00', $payable->outstanding_amount);
    }

    public function test_same_purchase_source_cannot_create_two_payables(): void
    {
        $owner = $this->owner();
        $supplier = $this->supplier();
        $service = app(\App\Services\SupplierPayableService::class);
        $data = [
            'supplier_id' => $supplier->id,
            'debt_date' => '2026-12-01',
            'original_amount' => '2500000000.99',
            'source_type' => \App\Models\StockPurchase::class,
            'source_id' => 77,
        ];

        $service->createPayable($owner, $this->outlet(), $data);

        $this->expectException(ValidationException::class);
        $service->createPayable($owner, $this->outlet(), $data);
    }

    public function test_database_unique_source_constraint_rejects_racing_duplicate(): void
    {
        $owner = $this->owner();
        $supplier = $this->supplier();
        $attributes = [
            'outlet_id' => $this->outlet()->id,
            'supplier_id' => $supplier->id,
            'source_type' => \App\Models\StockPurchase::class,
            'source_id' => 88,
            'debt_date' => '2026-12-01',
            'original_amount' => '100.00',
            'paid_amount' => '0.00',
            'outstanding_amount' => '100.00',
            'status' => 'open',
            'created_by' => $owner->id,
        ];

        SupplierPayable::create($attributes);

        $this->expectException(QueryException::class);
        SupplierPayable::create($attributes);
    }

    public function test_overpayment_duplicate_and_paid_payment_are_rejected(): void
    {
        $owner = $this->owner(); $payable = $this->payable($owner, $this->supplier(), '100.00'); $key = (string) Str::uuid();
        $payload = ['payment_date' => '2026-12-02', 'amount' => '100.01', 'payment_method' => 'cash', 'idempotency_key' => $key];
        $this->actingAs($owner)->post(route('payables.payments.store', $payable), $payload)->assertSessionHasErrors('amount');
        $payload['amount'] = '100.00'; $this->actingAs($owner)->post(route('payables.payments.store', $payable), $payload)->assertRedirect();
        $this->actingAs($owner)->post(route('payables.payments.store', $payable), $payload)->assertSessionHasErrors('idempotency_key'); $this->assertDatabaseCount('payable_payments', 1);
    }

    public function test_active_accounting_journals_payment_once_and_manual_new_debt_is_blocked(): void
    {
        $owner = $this->owner(); $supplier = $this->supplier(); $payable = $this->payable($owner, $supplier, '100.00'); $this->accounts();
        app(OpeningBalanceService::class)->post($owner, $this->outlet(), '2026-12-01', ['cash_balance' => '1000.00', 'bank_balance' => '0.00', 'inventory_balance' => '0.00', 'fixed_asset_balance' => '0.00', 'liabilities_balance' => '0.00', 'capital_balance' => '1000.00']);
        $this->actingAs($owner)->post(route('payables.payments.store', $payable), ['payment_date' => '2026-12-02', 'amount' => '25.00', 'payment_method' => 'cash', 'idempotency_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertDatabaseHas('journal_entries', ['journal_type' => 'payable_payment']); $this->assertDatabaseCount('journal_entries', 2);
        $this->actingAs($owner)->post(route('payables.store'), ['supplier_id' => $supplier->id, 'debt_date' => '2026-12-02', 'original_amount' => '50.00'])->assertSessionHasErrors('accounting'); $this->assertDatabaseCount('supplier_payables', 1);
    }

    public function test_failed_active_payment_rolls_back_payment_and_payable(): void
    {
        $owner = $this->owner(); $payable = $this->payable($owner, $this->supplier(), '100.00'); $this->accounts(false);
        app(OpeningBalanceService::class)->post($owner, $this->outlet(), '2026-12-01', ['cash_balance' => '1000.00', 'bank_balance' => '0.00', 'inventory_balance' => '0.00', 'fixed_asset_balance' => '0.00', 'liabilities_balance' => '0.00', 'capital_balance' => '1000.00']);
        $this->actingAs($owner)->post(route('payables.payments.store', $payable), ['payment_date' => '2026-12-02', 'amount' => '25.00', 'payment_method' => 'qris', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('accounting');
        $this->assertDatabaseCount('payable_payments', 0); $this->assertDatabaseHas('supplier_payables', ['id' => $payable->id, 'paid_amount' => '0.00']);
    }
}
