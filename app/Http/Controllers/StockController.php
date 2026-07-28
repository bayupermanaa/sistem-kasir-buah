<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdjustStockRequest;
use App\Http\Requests\UpdateMinimumStockRequest;
use App\Models\OutletProductStock;
use App\Models\Product;
use App\Services\SingleOutletService;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockController extends Controller
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly SingleOutletService $singleOutletService
    )
    {
    }

    public function index(Request $request): View
    {
        $outlet = $this->singleOutletService->getDefaultOutlet();

        $stockQuery = OutletProductStock::with(['outlet', 'product.category', 'product.unit'])
            ->where('outlet_id', $outlet->id)
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->whereHas('product', function ($productQuery) use ($search): void {
                    $productQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->latest('id');

        $lowStockCount = (clone $stockQuery)->whereColumn('quantity', '<=', 'minimum_stock')->count();

        $stocks = $stockQuery->paginate(10)->withQueryString();

        return view('products.index', compact('stocks', 'lowStockCount', 'outlet'));
    }

    public function createAdjustmentForm(Product $product, Request $request): View
    {
        $outlet = $this->singleOutletService->getDefaultOutlet();

        $stocks = OutletProductStock::with('outlet')
            ->where('product_id', $product->id)
            ->where('outlet_id', $outlet->id)
            ->first();

        return view('stocks.adjust', [
            'product' => $product->load(['category', 'unit']),
            'outlet' => $outlet,
            'stock' => $stocks,
            'types' => StockService::MANUAL_TYPES,
        ]);
    }

    public function storeAdjustment(AdjustStockRequest $request, Product $product): RedirectResponse
    {
        $outlet = $this->singleOutletService->getDefaultOutlet();

        $this->stockService->adjustStock(
            user: $request->user(),
            outlet: $outlet,
            product: $product,
            type: $request->string('type')->toString(),
            quantity: (float) $request->input('quantity'),
            reason: $request->string('reason')->toString(),
            notes: $request->filled('notes') ? $request->string('notes')->toString() : null,
        );

        return redirect()->route('products.index')->with('success', 'Penyesuaian stok berhasil disimpan.');
    }

    public function editMinimumStock(OutletProductStock $outletProductStock): View
    {
        return view('stocks.minimum-stock', [
            'stock' => $outletProductStock->load(['outlet', 'product']),
        ]);
    }

    public function updateMinimumStock(UpdateMinimumStockRequest $request, OutletProductStock $outletProductStock): RedirectResponse
    {
        $this->stockService->updateMinimumStock(
            user: $request->user(),
            stock: $outletProductStock->load('outlet'),
            minimumStock: (float) $request->input('minimum_stock')
        );

        return redirect()->route('products.index')->with('success', 'Minimum stok berhasil diperbarui.');
    }
}
