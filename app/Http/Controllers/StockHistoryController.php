<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\StockHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StockHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();

        $historyQuery = StockHistory::with(['outlet', 'product', 'user'])
            ->when($user?->role === 'cashier', fn ($query) => $query->where('outlet_id', $user->outlet_id))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->whereHas('product', function ($productQuery) use ($search): void {
                    $productQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('outlet_id'), fn ($query) => $query->where('outlet_id', $request->integer('outlet_id')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')->toString()))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('date_to')))
            ->latest();

        $histories = $historyQuery->paginate(15)->withQueryString();
        $outlets = $user?->role === 'owner' ? Outlet::orderBy('name')->get() : collect();

        return view('stock-histories.index', [
            'histories' => $histories,
            'outlets' => $outlets,
            'types' => ['initial_stock', 'restock', 'adjustment_in', 'adjustment_out', 'damaged', 'processed', 'stock_opname', 'transfer_in', 'transfer_out', 'return_in', 'return_out', 'sale'],
        ]);
    }
}
