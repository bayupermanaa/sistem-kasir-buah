<?php

namespace App\Http\Controllers;

use App\Models\StockHistory;
use App\Services\SingleOutletService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockHistoryController extends Controller
{
    public function __construct(private readonly SingleOutletService $singleOutletService)
    {
    }

    public function index(Request $request): View
    {
        $outlet = $this->singleOutletService->getDefaultOutlet();

        $historyQuery = StockHistory::with(['outlet', 'product', 'user'])
            ->where('outlet_id', $outlet->id)
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->whereHas('product', function ($productQuery) use ($search): void {
                    $productQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('type'), function ($query) use ($request): void {
                $type = $request->string('type')->toString();
                $types = StockHistory::TYPE_GROUPS[$type]['types'] ?? [$type];
                $query->whereIn('type', $types);
            })
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('date_to')))
            ->latest();

        $histories = $historyQuery->paginate(15)->withQueryString();

        return view('stock-histories.index', [
            'histories' => $histories,
            'outlet' => $outlet,
            'types' => StockHistory::TYPE_GROUPS,
        ]);
    }
}
