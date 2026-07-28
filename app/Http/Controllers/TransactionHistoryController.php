<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\SingleOutletService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class TransactionHistoryController extends Controller
{
    public function __construct(private readonly SingleOutletService $singleOutletService)
    {
    }

    public function index(Request $request): View
    {
        $user = Auth::user();
        $outletId = $this->singleOutletService->getDefaultOutletId();
        $search = $request->string('search')->toString();
        $paymentMethod = $request->string('payment_method')->toString();
        $status = $request->string('status')->toString();
        $cashierId = $this->resolveCashierId($request, $user, $outletId);
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $transactionsQuery = $this->baseQuery($outletId)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('invoice_number', 'like', '%'.$search.'%');
            })
            ->when($dateFrom, fn ($query) => $query->whereDate('created_at', '>=', Carbon::parse($dateFrom)))
            ->when($dateTo, fn ($query) => $query->whereDate('created_at', '<=', Carbon::parse($dateTo)))
            ->when($cashierId, fn ($query) => $query->where('user_id', $cashierId))
            ->when($paymentMethod !== '', fn ($query) => $query->where('payment_method', $paymentMethod))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest('created_at');

        $filteredTransactions = $this->baseQuery($outletId)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('invoice_number', 'like', '%'.$search.'%');
            })
            ->when($dateFrom, fn ($query) => $query->whereDate('created_at', '>=', Carbon::parse($dateFrom)))
            ->when($dateTo, fn ($query) => $query->whereDate('created_at', '<=', Carbon::parse($dateTo)))
            ->when($cashierId, fn ($query) => $query->where('user_id', $cashierId))
            ->when($paymentMethod !== '', fn ($query) => $query->where('payment_method', $paymentMethod))
            ->when($status !== '', fn ($query) => $query->where('status', $status));

        $transactions = $transactionsQuery
            ->paginate(10)
            ->withQueryString();

        $summaryTransactions = (clone $filteredTransactions)->withCount('items');
        $summary = [
            'transaction_count' => (clone $summaryTransactions)->count(),
            'total_sales' => (float) (clone $summaryTransactions)->sum('total'),
            'total_items' => (int) TransactionItem::query()
                ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
                ->where('transactions.outlet_id', $outletId)
                ->when($search !== '', fn ($query) => $query->where('transactions.invoice_number', 'like', '%'.$search.'%'))
                ->when($dateFrom, fn ($query) => $query->whereDate('transactions.created_at', '>=', Carbon::parse($dateFrom)))
                ->when($dateTo, fn ($query) => $query->whereDate('transactions.created_at', '<=', Carbon::parse($dateTo)))
                ->when($cashierId, fn ($query) => $query->where('transactions.user_id', $cashierId))
                ->when($paymentMethod !== '', fn ($query) => $query->where('transactions.payment_method', $paymentMethod))
                ->when($status !== '', fn ($query) => $query->where('transactions.status', $status))
                ->count(),
            'total_weight' => (float) TransactionItem::query()
                ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
                ->where('transactions.outlet_id', $outletId)
                ->when($search !== '', fn ($query) => $query->where('transactions.invoice_number', 'like', '%'.$search.'%'))
                ->when($dateFrom, fn ($query) => $query->whereDate('transactions.created_at', '>=', Carbon::parse($dateFrom)))
                ->when($dateTo, fn ($query) => $query->whereDate('transactions.created_at', '<=', Carbon::parse($dateTo)))
                ->when($cashierId, fn ($query) => $query->where('transactions.user_id', $cashierId))
                ->when($paymentMethod !== '', fn ($query) => $query->where('transactions.payment_method', $paymentMethod))
                ->when($status !== '', fn ($query) => $query->where('transactions.status', $status))
                ->sum('transaction_items.quantity'),
        ];

        return view('transactions.index', [
            'transactions' => $transactions,
            'summary' => $summary,
            'cashiers' => $user?->role === 'owner'
                ? User::query()
                    ->where('role', 'cashier')
                    ->where('outlet_id', $outletId)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                : collect(),
            'filters' => [
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'cashier_id' => $cashierId,
                'payment_method' => $paymentMethod,
                'status' => $status,
            ],
            'paymentMethods' => Transaction::PAYMENT_METHOD_LABELS,
            'statuses' => Transaction::STATUS_LABELS,
        ]);
    }

    private function baseQuery(int $outletId): Builder
    {
        return Transaction::query()
            ->with(['user:id,name'])
            ->withCount('items')
            ->where('outlet_id', $outletId);
    }

    private function resolveCashierId(Request $request, ?User $user, int $outletId): ?int
    {
        if ($user?->role !== 'owner') {
            return null;
        }

        $cashierId = $request->integer('cashier_id');

        if (! $cashierId) {
            return null;
        }

        return User::query()
            ->whereKey($cashierId)
            ->where('role', 'cashier')
            ->where('outlet_id', $outletId)
            ->value('id');
    }
}
