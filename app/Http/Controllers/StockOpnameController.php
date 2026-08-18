<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockOpnameRequest;
use App\Models\OutletProductStock;
use App\Models\StockOpname;
use App\Services\SingleOutletService;
use App\Services\StockOpnameService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockOpnameController extends Controller
{
    public function __construct(
        private readonly StockOpnameService $stockOpnameService,
        private readonly SingleOutletService $singleOutletService
    ) {
    }

    public function index(Request $request): View
    {
        $outlet = $this->singleOutletService->getDefaultOutlet();

        $stockOpnames = StockOpname::query()
            ->with(['creator:id,name', 'items'])
            ->withCount('items')
            ->where('outlet_id', $outlet->id)
            ->latest('opname_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $stockOpnames->getCollection()->transform(function (StockOpname $opname): StockOpname {
            $opname->setAttribute('total_difference', round($opname->items->sum(fn ($item): float => abs((float) $item->difference)), 3));

            return $opname;
        });

        $summary = [
            'session_count' => $stockOpnames->total(),
            'product_count' => $stockOpnames->getCollection()->sum('items_count'),
        ];

        return view('stock-opnames.index', [
            'outlet' => $outlet,
            'stockOpnames' => $stockOpnames,
            'summary' => $summary,
        ]);
    }

    public function create(): View
    {
        $outlet = $this->singleOutletService->getDefaultOutlet();

        $stocks = OutletProductStock::query()
            ->with(['product.category', 'product.unit'])
            ->where('outlet_id', $outlet->id)
            ->whereHas('product', fn ($query) => $query->active())
            ->get()
            ->sortBy(fn (OutletProductStock $stock) => strtolower($stock->product?->name ?? ''))
            ->values();

        return view('stock-opnames.create', [
            'outlet' => $outlet,
            'stocks' => $stocks,
        ]);
    }

    public function store(StoreStockOpnameRequest $request): RedirectResponse
    {
        $opname = $this->stockOpnameService->store($request->user(), $request->validated());

        return redirect()
            ->route('stock.opnames.show', $opname)
            ->with('success', 'Stock Opname berhasil disimpan.');
    }

    public function show(StockOpname $stockOpname): View
    {
        $this->ensureDefaultOutletStockOpname($stockOpname);

        return view('stock-opnames.show', [
            'stockOpname' => $stockOpname->load([
                'outlet',
                'creator:id,name',
                'items.product.unit',
                'items.product.category',
            ]),
        ]);
    }

    private function ensureDefaultOutletStockOpname(StockOpname $stockOpname): void
    {
        abort_unless((int) $stockOpname->outlet_id === $this->singleOutletService->getDefaultOutletId(), 404);
    }
}
