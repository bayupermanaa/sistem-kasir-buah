<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdjustStockRequest;
use App\Http\Requests\UpdateMinimumStockRequest;
use App\Models\Outlet;
use App\Models\OutletProductStock;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StockController extends Controller
{
    public function __construct(private readonly StockService $stockService)
    {
    }

    public function index(Request $request): View
    {
        $user = Auth::user();
        $outletId = $user?->role === 'owner'
            ? $request->integer('outlet_id') ?: null
            : $user?->outlet_id;

        $stockQuery = OutletProductStock::with(['outlet', 'product.category', 'product.unit'])
            ->when($outletId, fn ($query) => $query->where('outlet_id', $outletId))
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
        $outlets = $user?->role === 'owner' ? Outlet::orderBy('name')->get() : collect();

        return view('products.index', compact('stocks', 'outlets', 'lowStockCount', 'outletId'));
    }

    public function createAdjustmentForm(Product $product, Request $request): View
    {
        $user = Auth::user();

        if ($user?->role === 'cashier') {
            $outlet = Outlet::findOrFail($user->outlet_id);
            $stocks = OutletProductStock::with('outlet')->where('product_id', $product->id)->where('outlet_id', $outlet->id)->first();
        } else {
            $stocks = null;
        }

        $outlets = $user?->role === 'owner'
            ? Outlet::orderBy('name')->get()
            : collect([Outlet::findOrFail($user?->outlet_id)]);

        return view('stocks.adjust', [
            'product' => $product->load(['category', 'unit']),
            'outlets' => $outlets,
            'selectedOutletId' => $request->integer('outlet_id') ?: $user?->outlet_id,
            'stock' => $stocks,
            'types' => StockService::MANUAL_TYPES,
        ]);
    }

    public function storeAdjustment(AdjustStockRequest $request, Product $product): RedirectResponse
    {
        $outlet = Outlet::findOrFail($request->integer('outlet_id'));

        $this->stockService->adjustStock(
            user: $request->user(),
            outlet: $outlet,
            product: $product,
            type: $request->string('type')->toString(),
            quantity: (float) $request->input('quantity'),
            reason: $request->string('reason')->toString(),
            notes: $request->filled('notes') ? $request->string('notes')->toString() : null,
        );

        return redirect()->route('products.index', ['outlet_id' => $outlet->id])->with('success', 'Penyesuaian stok berhasil disimpan.');
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

        return redirect()->route('products.index', ['outlet_id' => $outletProductStock->outlet_id])->with('success', 'Minimum stok berhasil diperbarui.');
    }
}
