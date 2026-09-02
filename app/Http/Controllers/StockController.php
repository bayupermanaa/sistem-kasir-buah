<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdjustStockRequest;
use App\Http\Requests\UpdateMinimumStockRequest;
use App\Http\Requests\StoreStockPurchaseRequest;
use App\Models\Category;
use App\Models\OutletProductStock;
use App\Models\Product;
use App\Models\StockHistory;
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
        $selectedStatus = $request->string('status')->toString();
        $selectedCategoryId = $request->filled('category_id') ? $request->integer('category_id') : null;

        $stockQuery = OutletProductStock::with(['outlet', 'product.category', 'product.unit'])
            ->where('outlet_id', $outlet->id)
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->whereHas('product', function ($productQuery) use ($search): void {
                    $productQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($selectedCategoryId, function ($query) use ($selectedCategoryId): void {
                $query->whereHas('product', function ($productQuery) use ($selectedCategoryId): void {
                    $productQuery->where('category_id', $selectedCategoryId);
                });
            })
            ->latest('id');

        $lowStockCount = (clone $stockQuery)->whereColumn('quantity', '<=', 'minimum_stock')->count();

        $stockQuery->when($selectedStatus !== '' && in_array($selectedStatus, ['safe', 'low', 'out'], true), function ($query) use ($selectedStatus): void {
            if ($selectedStatus === 'out') {
                $query->where('quantity', '<=', 0);

                return;
            }

            if ($selectedStatus === 'low') {
                $query->where('quantity', '>', 0)
                    ->whereColumn('quantity', '<=', 'minimum_stock');

                return;
            }

            $query->whereColumn('quantity', '>', 'minimum_stock');
        });

        $stocks = $stockQuery->paginate(10)->withQueryString();

        return view('products.index', [
            'stocks' => $stocks,
            'lowStockCount' => $lowStockCount,
            'outlet' => $outlet,
            'categories' => Category::orderBy('name')->get(),
            'selectedStatus' => $selectedStatus,
            'selectedCategoryId' => $selectedCategoryId,
            'statusOptions' => [
                'safe' => 'Aman',
                'low' => 'Menipis',
                'out' => 'Habis',
            ],
        ]);
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
            'typeOptions' => StockHistory::TYPE_META,
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

    public function createPurchase(Product $product): View
    {
        $outlet = $this->singleOutletService->getDefaultOutlet();
        $stock = OutletProductStock::where('outlet_id', $outlet->id)->where('product_id', $product->id)->first();

        return view('stocks.purchase', compact('product', 'stock', 'outlet'));
    }

    public function storePurchase(StoreStockPurchaseRequest $request, Product $product): RedirectResponse
    {
        $outlet = $this->singleOutletService->getDefaultOutlet();
        $this->stockService->purchaseStock(
            $request->user(), $outlet, $product,
            (float) $request->input('quantity'),
            $request->filled('unit_cost') ? (float) $request->input('unit_cost') : null,
            $request->date('purchase_date')->toDateString(),
            $request->filled('payment_method') ? $request->string('payment_method')->toString() : null,
            $request->filled('notes') ? $request->string('notes')->toString() : null,
        );

        return redirect()->route('products.index')->with('success', 'Pembelian stok berhasil dicatat.');
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
