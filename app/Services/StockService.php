<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\OutletProductStock;
use App\Models\Product;
use App\Models\StockHistory;
use App\Models\StockPurchase;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    public function __construct(private readonly SingleOutletService $singleOutletService)
    {
    }

    public const MANUAL_TYPES = [
        'restock',
        'adjustment_in',
        'adjustment_out',
        'damaged',
        'processed',
        'return_in',
        'return_out',
    ];

    public const ALL_TYPES = [
        'initial_stock',
        ...self::MANUAL_TYPES,
        'stock_opname',
        'transfer_in',
        'transfer_out',
        'sale',
    ];

    public function adjustStock(
        User $user,
        Outlet $outlet,
        Product $product,
        string $type,
        float $quantity,
        string $reason,
        ?string $notes = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): StockHistory {
        if (! in_array($type, self::MANUAL_TYPES, true)) {
            throw ValidationException::withMessages([
                'type' => 'Jenis perubahan stok tidak diizinkan.',
            ]);
        }

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Jumlah harus lebih besar dari 0.',
            ]);
        }

        $this->assertUserCanAccessOutlet($user, $outlet);

        return DB::transaction(function () use ($user, $outlet, $product, $type, $quantity, $reason, $notes, $referenceType, $referenceId): StockHistory {
            $stock = $this->lockStock($outlet->id, $product->id);
            $quantityBefore = (float) $stock->quantity;
            $change = $this->changeValue($type, $quantity);
            $quantityAfter = $quantityBefore + $change;

            if ($quantityAfter < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Stok tidak boleh negatif.',
                ]);
            }

            $stock->quantity = $quantityAfter;
            $stock->save();

            return StockHistory::create([
                'outlet_id' => $outlet->id,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'type' => $type,
                'quantity_before' => $quantityBefore,
                'quantity_change' => $change,
                'quantity_after' => $quantityAfter,
                'reason' => $reason,
                'notes' => $notes,
            ]);
        });
    }

    public function initializeStock(
        User $user,
        Outlet $outlet,
        Product $product,
        float $quantity,
        float $minimumStock,
        ?string $reason = 'Initial stock',
        ?string $notes = null
    ): StockHistory {
        return DB::transaction(function () use ($user, $outlet, $product, $quantity, $minimumStock, $reason, $notes): StockHistory {
            $this->assertUserCanAccessOutlet($user, $outlet);

            $stock = $this->lockStock($outlet->id, $product->id);
            $quantityBefore = (float) $stock->quantity;
            $stock->minimum_stock = $minimumStock;
            $stock->quantity = $quantity;
            $stock->save();

            return StockHistory::create([
                'outlet_id' => $outlet->id,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'reference_type' => null,
                'reference_id' => null,
                'type' => 'initial_stock',
                'quantity_before' => $quantityBefore,
                'quantity_change' => $quantity - $quantityBefore,
                'quantity_after' => $quantity,
                'reason' => $reason,
                'notes' => $notes,
            ]);
        });
    }

    public function initializeProductStocks(Product $product): void
    {
        OutletProductStock::firstOrCreate(
            [
                'outlet_id' => $this->singleOutletService->getDefaultOutletId(),
                'product_id' => $product->id,
            ],
            [
                'quantity' => 0,
                'minimum_stock' => 0,
            ]
        );
    }

    public function purchaseStock(User $user, Outlet $outlet, Product $product, float $quantity, ?float $unitCost, string $purchaseDate, ?string $paymentMethod, ?string $notes = null): StockPurchase
    {
        $this->assertUserCanAccessOutlet($user, $outlet);

        return DB::transaction(function () use ($user, $outlet, $product, $quantity, $unitCost, $purchaseDate, $paymentMethod, $notes): StockPurchase {
            $stock = $this->lockStock($outlet->id, $product->id);
            $before = (float) $stock->quantity;
            $after = round($before + $quantity, 3);
            $stock->quantity = $after;
            $stock->save();

            $purchase = StockPurchase::create([
                'outlet_id' => $outlet->id,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $unitCost === null ? null : round($quantity * $unitCost, 2),
                'purchase_date' => $purchaseDate,
                'payment_method' => $paymentMethod,
                'notes' => $notes,
            ]);

            StockHistory::create([
                'outlet_id' => $outlet->id,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'reference_type' => StockPurchase::class,
                'reference_id' => $purchase->id,
                'type' => 'restock',
                'quantity_before' => $before,
                'quantity_change' => $quantity,
                'quantity_after' => $after,
                'reason' => 'Pembelian stok',
                'notes' => $notes,
            ]);

            return $purchase->load(['outlet', 'product', 'user']);
        });
    }
    public function updateMinimumStock(User $user, OutletProductStock $stock, float $minimumStock): OutletProductStock
    {
        $this->assertUserCanAccessOutlet($user, $stock->outlet);

        return DB::transaction(function () use ($stock, $minimumStock): OutletProductStock {
            $lockedStock = OutletProductStock::whereKey($stock->id)->lockForUpdate()->firstOrFail();
            $lockedStock->minimum_stock = $minimumStock;
            $lockedStock->save();

            return $lockedStock;
        });
    }

    public function lockStock(int $outletId, int $productId): OutletProductStock
    {
        $stock = OutletProductStock::where('outlet_id', $outletId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if ($stock) {
            return $stock;
        }

        OutletProductStock::create([
            'outlet_id' => $outletId,
            'product_id' => $productId,
            'quantity' => 0,
            'minimum_stock' => 0,
        ]);

        return OutletProductStock::where('outlet_id', $outletId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function changeValue(string $type, float $quantity): float
    {
        return in_array($type, ['adjustment_out', 'damaged', 'processed', 'return_out'], true)
            ? -abs($quantity)
            : abs($quantity);
    }

    private function assertUserCanAccessOutlet(User $user, Outlet $outlet): void
    {
        if ((int) $outlet->id !== $this->singleOutletService->getDefaultOutletId()) {
            throw new AuthorizationException('Anda hanya dapat mengakses toko utama.');
        }

        if ($user->role === 'owner' || ($user->role === 'cashier' && (int) $user->outlet_id === (int) $outlet->id)) {
            return;
        }

        throw new AuthorizationException('Anda tidak memiliki akses ke toko ini.');
    }
}
