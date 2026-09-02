<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAssetRequest;
use App\Http\Requests\UpdateAssetRequest;
use App\Models\Asset;
use App\Services\SingleOutletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AssetController extends Controller
{
    public function __construct(private readonly SingleOutletService $singleOutletService)
    {
    }

    public function index(Request $request): View
    {
        $outlet = $this->singleOutletService->getDefaultOutlet();
        $search = trim($request->string('search')->toString());
        $category = $request->string('category')->toString();
        $status = $request->string('status')->toString();

        $assetsQuery = Asset::query()
            ->with(['creator:id,name', 'outlet:id,name'])
            ->forOutlet($outlet->id)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('asset_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest('purchase_date')
            ->latest('id');

        $summaryQuery = clone $assetsQuery;

        return view('assets.index', [
            'assets' => $assetsQuery->paginate(10)->withQueryString(),
            'summary' => [
                'count' => (clone $summaryQuery)->count(),
                'total_cost' => (float) (clone $summaryQuery)->sum('total_cost'),
                'active_count' => (clone $summaryQuery)->where('status', 'Aktif')->count(),
            ],
            'filters' => [
                'search' => $search,
                'category' => $category,
                'status' => $status,
            ],
            'categories' => Asset::categories(),
            'statusOptions' => Asset::statuses(),
            'outlet' => $outlet,
        ]);
    }

    public function create(): View
    {
        return view('assets.create', [
            'categories' => Asset::categories(),
            'statusOptions' => Asset::statuses(),
            'paymentMethods' => Asset::paymentMethods(),
        ]);
    }

    public function store(StoreAssetRequest $request): RedirectResponse
    {
        $outlet = $this->singleOutletService->getDefaultOutlet();

        DB::transaction(function () use ($request, $outlet): void {
            $purchasePrice = (float) $request->input('purchase_price');
            $quantity = (int) $request->input('quantity');

            Asset::create([
                'outlet_id' => $outlet->id,
                'asset_code' => Asset::generateAutoCode(),
                'name' => $request->string('name')->toString(),
                'category' => $request->string('category')->toString(),
                'purchase_date' => $request->date('purchase_date'),
                'purchase_price' => $purchasePrice,
                'quantity' => $quantity,
                'total_cost' => round($purchasePrice * $quantity, 2),
                'payment_method' => $request->filled('payment_method') ? $request->string('payment_method')->toString() : null,
                'useful_life_years' => $request->filled('useful_life_years') ? $request->integer('useful_life_years') : null,
                'residual_value' => $request->filled('residual_value') ? (float) $request->input('residual_value') : 0,
                'status' => $request->string('status')->toString(),
                'notes' => $request->filled('notes') ? $request->string('notes')->toString() : null,
                'created_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('assets.index')->with('success', 'Aset berhasil ditambahkan.');
    }

    public function show(Asset $asset): View
    {
        $this->ensureDefaultOutletAsset($asset);

        return view('assets.show', [
            'asset' => $asset->load(['outlet', 'creator']),
        ]);
    }

    public function edit(Asset $asset): View
    {
        $this->ensureDefaultOutletAsset($asset);

        return view('assets.edit', [
            'asset' => $asset->load(['outlet', 'creator']),
            'categories' => Asset::categories(),
            'statusOptions' => Asset::statuses(),
            'paymentMethods' => Asset::paymentMethods(),
        ]);
    }

    public function update(UpdateAssetRequest $request, Asset $asset): RedirectResponse
    {
        $this->ensureDefaultOutletAsset($asset);

        DB::transaction(function () use ($request, $asset): void {
            $purchasePrice = (float) $request->input('purchase_price');
            $quantity = (int) $request->input('quantity');

            $asset->update([
                'name' => $request->string('name')->toString(),
                'category' => $request->string('category')->toString(),
                'purchase_date' => $request->date('purchase_date'),
                'purchase_price' => $purchasePrice,
                'quantity' => $quantity,
                'total_cost' => round($purchasePrice * $quantity, 2),
                'payment_method' => $request->filled('payment_method') ? $request->string('payment_method')->toString() : null,
                'useful_life_years' => $request->filled('useful_life_years') ? $request->integer('useful_life_years') : null,
                'residual_value' => $request->filled('residual_value') ? (float) $request->input('residual_value') : 0,
                'status' => $request->string('status')->toString(),
                'notes' => $request->filled('notes') ? $request->string('notes')->toString() : null,
            ]);
        });

        return redirect()->route('assets.index')->with('success', 'Aset berhasil diperbarui.');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        $this->ensureDefaultOutletAsset($asset);

        $asset->delete();

        return redirect()->route('assets.index')->with('success', 'Aset berhasil dihapus.');
    }

    private function ensureDefaultOutletAsset(Asset $asset): void
    {
        abort_unless((int) $asset->outlet_id === $this->singleOutletService->getDefaultOutletId(), 404);
    }
}
