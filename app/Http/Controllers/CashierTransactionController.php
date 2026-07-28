<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Models\Category;
use App\Models\OutletProductStock;
use App\Models\Transaction;
use App\Services\SingleOutletService;
use App\Services\TransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashierTransactionController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactionService,
        private readonly SingleOutletService $singleOutletService
    )
    {
    }

    public function index(Request $request): View
    {
        $outlet = $this->singleOutletService->getDefaultOutlet();
        $categories = Category::orderBy('name')->get();
        $search = $request->string('search')->toString();
        $selectedCategoryId = $request->integer('category_id') ?: null;

        $stockQuery = OutletProductStock::query()
            ->with(['product.category', 'product.unit'])
            ->where('outlet_id', $outlet->id)
            ->where('quantity', '>', 0)
            ->whereHas('product', fn ($query) => $query->where('is_active', true));

        if ($search !== '') {
            $stockQuery->whereHas('product', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('name', 'like', '%'.$search.'%')
                        ->orWhere('code', 'like', '%'.$search.'%');
                });
            });
        }

        if ($selectedCategoryId) {
            $stockQuery->whereHas('product', function ($query) use ($selectedCategoryId): void {
                $query->where('category_id', $selectedCategoryId);
            });
        }

        $stocks = $stockQuery
            ->get()
            ->sortBy(fn (OutletProductStock $stock) => strtolower($stock->product?->name ?? ''))
            ->values();

        return view('kasir.index', [
            'stocks' => $stocks,
            'selectedOutlet' => $outlet,
            'categories' => $categories,
            'search' => $search,
            'selectedCategoryId' => $selectedCategoryId,
        ]);
    }

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        $transaction = $this->transactionService->store($request->user(), $request->validated());

        return redirect()
            ->route('kasir.show', $transaction)
            ->with('success', 'Transaksi berhasil disimpan.');
    }

    public function show(Transaction $transaction, Request $request): View
    {
        $this->authorizeTransactionAccess($request, $transaction);

        return view('kasir.show', [
            'transaction' => $transaction->load(['outlet', 'user', 'items.product.category', 'items.product.unit']),
        ]);
    }

    public function print(Transaction $transaction, Request $request): View
    {
        $this->authorizeTransactionAccess($request, $transaction);

        return view('kasir.print', [
            'transaction' => $transaction->load(['outlet', 'user', 'items.product.category', 'items.product.unit']),
        ]);
    }

    private function authorizeTransactionAccess(Request $request, Transaction $transaction): void
    {
        $user = $request->user();

        if ($user?->role === 'cashier' && (int) $transaction->outlet_id !== (int) $user->outlet_id) {
            abort(403);
        }
    }
}
