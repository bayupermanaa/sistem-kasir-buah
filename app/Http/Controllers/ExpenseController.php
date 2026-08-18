<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use App\Services\SingleOutletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function __construct(private readonly SingleOutletService $singleOutletService)
    {
    }

    public function index(Request $request): View
    {
        $outlet = $this->singleOutletService->getDefaultOutlet();
        $search = $request->string('search')->toString();
        $category = $request->string('category')->toString();
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $expensesQuery = Expense::query()
            ->with(['creator:id,name', 'outlet:id,name'])
            ->forOutlet($outlet->id)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('description', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->when($dateFrom, fn ($query) => $query->whereDate('expense_date', '>=', Carbon::parse($dateFrom)))
            ->when($dateTo, fn ($query) => $query->whereDate('expense_date', '<=', Carbon::parse($dateTo)))
            ->latest('expense_date')
            ->latest('id');

        $summaryQuery = clone $expensesQuery;

        return view('expenses.index', [
            'expenses' => $expensesQuery->paginate(10)->withQueryString(),
            'summary' => [
                'count' => (clone $summaryQuery)->count(),
                'amount' => (float) (clone $summaryQuery)->sum('amount'),
            ],
            'categories' => Expense::categories(),
            'filters' => [
                'search' => $search,
                'category' => $category,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'outlet' => $outlet,
        ]);
    }

    public function create(): View
    {
        return view('expenses.create', [
            'categories' => Expense::categories(),
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $outlet = $this->singleOutletService->getDefaultOutlet();

        Expense::create([
            'outlet_id' => $outlet->id,
            'expense_date' => $request->date('expense_date'),
            'category' => $request->string('category')->toString(),
            'description' => $request->string('description')->toString(),
            'amount' => $request->input('amount'),
            'notes' => $request->filled('notes') ? $request->string('notes')->toString() : null,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('expenses.index')->with('success', 'Pengeluaran berhasil ditambahkan.');
    }

    public function edit(Expense $expense): View
    {
        $this->ensureDefaultOutletExpense($expense);

        return view('expenses.edit', [
            'expense' => $expense,
            'categories' => Expense::categories(),
        ]);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->ensureDefaultOutletExpense($expense);

        $expense->update([
            'expense_date' => $request->date('expense_date'),
            'category' => $request->string('category')->toString(),
            'description' => $request->string('description')->toString(),
            'amount' => $request->input('amount'),
            'notes' => $request->filled('notes') ? $request->string('notes')->toString() : null,
        ]);

        return redirect()->route('expenses.index')->with('success', 'Pengeluaran berhasil diperbarui.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->ensureDefaultOutletExpense($expense);

        $expense->delete();

        return redirect()->route('expenses.index')->with('success', 'Pengeluaran berhasil dihapus.');
    }

    private function ensureDefaultOutletExpense(Expense $expense): void
    {
        $outletId = $this->singleOutletService->getDefaultOutletId();

        abort_unless((int) $expense->outlet_id === $outletId, 404);
        abort_unless(auth()->user()?->role === 'owner', 403);
    }
}
