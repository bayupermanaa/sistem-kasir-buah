<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAssetRequest;
use App\Http\Requests\UpdateAssetRequest;
use App\Models\Asset;
use App\Models\AccountingSetting;
use App\Services\AssetAccountingService;
use App\Services\SingleOutletService;
use App\Support\DecimalMath;
use Illuminate\Support\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AssetController extends Controller
{
    public function __construct(
        private readonly SingleOutletService $singleOutletService,
        private readonly AssetAccountingService $assetAccountingService,
    )
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
            ->withExists('journalEntries')
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
            $purchasePrice = $request->string('purchase_price')->toString();
            $quantity = (int) $request->input('quantity');

            $asset = Asset::create([
                'outlet_id' => $outlet->id,
                'asset_code' => Asset::generateAutoCode(),
                'name' => $request->string('name')->toString(),
                'category' => $request->string('category')->toString(),
                'purchase_date' => $request->date('purchase_date'),
                'purchase_price' => $purchasePrice,
                'quantity' => $quantity,
                'total_cost' => $this->calculateTotalCost($purchasePrice, $quantity),
                'payment_method' => $request->filled('payment_method') ? $request->string('payment_method')->toString() : null,
                'useful_life_years' => $request->filled('useful_life_years') ? $request->integer('useful_life_years') : null,
                'residual_value' => $request->filled('residual_value') ? $request->string('residual_value')->toString() : '0.00',
                'status' => $request->string('status')->toString(),
                'notes' => $request->filled('notes') ? $request->string('notes')->toString() : null,
                'created_by' => $request->user()->id,
            ]);

            $this->assetAccountingService->recordAsset($asset->load('creator'));
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
        abort_unless(! $this->assetAccountingService->hasJournal($asset), 422, 'Aset yang sudah dijurnal tidak dapat diedit.');

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
        abort_unless(! $this->assetAccountingService->hasJournal($asset), 422, 'Aset yang sudah dijurnal tidak dapat diedit.');

        DB::transaction(function () use ($request, $asset): void {
            $asset = Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $this->ensureDefaultOutletAsset($asset);
            abort_unless(! $this->assetAccountingService->hasJournal($asset), 422, 'Aset yang sudah dijurnal tidak dapat diedit.');
            $this->assertUpdatePolicy($request, $asset);

            $purchasePrice = $request->string('purchase_price')->toString();
            $quantity = (int) $request->input('quantity');

            $asset->update([
                'name' => $request->string('name')->toString(),
                'category' => $request->string('category')->toString(),
                'purchase_date' => $request->date('purchase_date'),
                'purchase_price' => $purchasePrice,
                'quantity' => $quantity,
                'total_cost' => $this->calculateTotalCost($purchasePrice, $quantity),
                'payment_method' => $request->filled('payment_method') ? $request->string('payment_method')->toString() : null,
                'useful_life_years' => $request->filled('useful_life_years') ? $request->integer('useful_life_years') : null,
                'residual_value' => $request->filled('residual_value') ? $request->string('residual_value')->toString() : '0.00',
                'status' => $request->string('status')->toString(),
                'notes' => $request->filled('notes') ? $request->string('notes')->toString() : null,
            ]);
        });

        return redirect()->route('assets.index')->with('success', 'Aset berhasil diperbarui.');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        $this->ensureDefaultOutletAsset($asset);
        abort_unless(! $this->assetAccountingService->hasJournal($asset), 422, 'Aset yang sudah dijurnal tidak dapat dihapus.');

        $asset->delete();

        return redirect()->route('assets.index')->with('success', 'Aset berhasil dihapus.');
    }

    private function ensureDefaultOutletAsset(Asset $asset): void
    {
        abort_unless((int) $asset->outlet_id === $this->singleOutletService->getDefaultOutletId(), 404);
    }

    private function calculateTotalCost(string $purchasePrice, int $quantity): string
    {
        [$whole, $fraction] = array_pad(explode('.', trim($purchasePrice), 2), 2, '0');
        $cents = ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
        $totalCents = $cents * $quantity;

        return sprintf('%d.%02d', intdiv($totalCents, 100), $totalCents % 100);
    }

    private function assertUpdatePolicy(UpdateAssetRequest $request, Asset $asset): void
    {
        $cutoverDate = AccountingSetting::query()->where('outlet_id', $asset->outlet_id)->value('cutover_date');
        if (! $cutoverDate) {
            return;
        }

        $cutover = Carbon::parse($cutoverDate)->startOfDay();
        $requestedDate = $request->date('purchase_date')->startOfDay();
        $originalDate = $asset->purchase_date->startOfDay();

        if ($originalDate->lt($cutover) && ! $requestedDate->lt($cutover)) {
            abort(422, 'Aset historis tidak boleh diubah menjadi pembelian setelah tanggal cutover.');
        }

        $requestedPrice = DecimalMath::format(DecimalMath::parse($request->string('purchase_price')->toString(), 2), 2);
        $originalPrice = DecimalMath::format(DecimalMath::parse($asset->purchase_price, 2), 2);
        $requestedPayment = $request->filled('payment_method') ? $request->string('payment_method')->toString() : null;
        $originalPayment = $asset->payment_method ?: null;

        $financialChanged = $requestedDate->toDateString() !== $originalDate->toDateString()
            || $requestedPrice !== $originalPrice
            || (int) $request->input('quantity') !== (int) $asset->quantity
            || $requestedPayment !== $originalPayment;

        if ($financialChanged) {
            abort(422, 'Tanggal, nilai, jumlah, dan metode pembayaran aset yang belum dijurnal tidak dapat diubah saat pembukuan aktif.');
        }
    }
}
