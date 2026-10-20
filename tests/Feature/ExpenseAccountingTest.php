<?php

namespace Tests\Feature;

use App\Models\AccountingAccount;
use App\Models\AccountingSetting;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\Outlet;
use App\Models\User;
use App\Services\ExpenseAccountingService;
use App\Services\OpeningBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExpenseAccountingTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $outlet = Outlet::firstOrCreate(['name' => Outlet::DEFAULT_NAME]);

        return User::factory()->create([
            'name' => 'Owner Expense Accounting',
            'username' => 'owner-expense-accounting-'.Str::random(6),
            'email' => Str::random(8).'@example.test',
            'role' => 'owner',
            'outlet_id' => $outlet->id,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
    }

    private function accounts(bool $includeExpense = true): void
    {
        $accounts = [
            ['code' => '1000', 'name' => 'Kas Tunai', 'type' => 'asset', 'subtype' => 'cash'],
            ['code' => '1010', 'name' => 'Bank/QRIS', 'type' => 'asset', 'subtype' => 'bank'],
            ['code' => '3000', 'name' => 'Modal Pemilik', 'type' => 'equity', 'subtype' => 'capital'],
        ];
        if ($includeExpense) {
            $accounts[] = ['code' => '6000', 'name' => 'Beban Operasional', 'type' => 'expense', 'subtype' => 'operating_expense'];
        }
        foreach ($accounts as $account) {
            AccountingAccount::create($account + ['is_active' => true]);
        }
    }

    private function activate(User $owner, bool $includeExpense = true): void
    {
        $this->accounts($includeExpense);
        app(OpeningBalanceService::class)->post($owner, $owner->outlet, '2026-01-01', [
            'cash_balance' => '100000.00',
            'bank_balance' => '0.00',
            'inventory_balance' => '0.00',
            'fixed_asset_balance' => '0.00',
            'liabilities_balance' => '0.00',
            'capital_balance' => '100000.00',
        ]);
    }

    public function test_active_expenses_support_cash_transfer_and_qris(): void
    {
        $owner = $this->owner();
        $this->activate($owner);

        foreach (['cash', 'transfer', 'qris'] as $index => $method) {
            $this->actingAs($owner)->post(route('expenses.store'), [
                'expense_date' => '2026-01-02',
                'category' => 'Operasional',
                'description' => 'Pengeluaran '.$method,
                'amount' => '1000000.10',
                'payment_method' => $method,
            ])->assertRedirect(route('expenses.index'));
        }

        $this->assertDatabaseCount('journal_entries', 4);
        $this->assertDatabaseCount('expenses', 3);
        $this->assertDatabaseHas('expenses', ['payment_method' => 'cash', 'amount' => '1000000.10']);
        $this->assertSame('3000000.30', number_format((float) JournalEntry::query()->where('journal_type', 'operating_expense')->with('lines')->get()->flatMap->lines->sum('debit'), 2, '.', ''));
    }

    public function test_expense_journal_is_balanced_and_not_duplicated(): void
    {
        $owner = $this->owner();
        $this->activate($owner);
        $this->actingAs($owner)->post(route('expenses.store'), [
            'expense_date' => '2026-01-02', 'category' => 'Listrik', 'description' => 'Tagihan listrik',
            'amount' => '250000.50', 'payment_method' => 'cash',
        ])->assertRedirect();

        $expense = Expense::firstOrFail();
        $journal = JournalEntry::query()->where('reference_type', Expense::class)->where('reference_id', $expense->id)->firstOrFail();
        $this->assertSame('250000.50', number_format((float) $journal->lines()->sum('debit'), 2, '.', ''));
        $this->assertSame('250000.50', number_format((float) $journal->lines()->sum('credit'), 2, '.', ''));

        $this->expectException(ValidationException::class);
        app(ExpenseAccountingService::class)->recordExpense($expense->load('creator'));
    }

    public function test_expense_before_cutover_is_rejected_when_accounting_active(): void
    {
        $owner = $this->owner();
        $this->activate($owner);

        $this->actingAs($owner)->post(route('expenses.store'), [
            'expense_date' => '2025-12-31', 'category' => 'Air', 'description' => 'Tanggal lama',
            'amount' => 10000, 'payment_method' => 'cash',
        ])->assertSessionHasErrors('expense_date');

        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_expense_rolls_back_when_accounting_account_is_missing(): void
    {
        $owner = $this->owner();
        $this->activate($owner, false);

        $this->actingAs($owner)->post(route('expenses.store'), [
            'expense_date' => '2026-01-02', 'category' => 'Gas', 'description' => 'Gas toko',
            'amount' => 50000, 'payment_method' => 'cash',
        ])->assertSessionHasErrors('accounting');

        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_before_accounting_active_expense_keeps_existing_flow_without_journal(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('expenses.store'), [
            'expense_date' => '2026-01-02', 'category' => 'Transportasi', 'description' => 'Ongkos kirim',
            'amount' => 75000,
        ])->assertRedirect(route('expenses.index'));

        $this->assertDatabaseCount('expenses', 1);
        $this->assertDatabaseCount('journal_entries', 0);
    }
}
