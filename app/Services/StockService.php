<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\OutletProductStock;
use App\Models\Product;
use App\Models\StockHistory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
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
        $outlets = Outlet::query()->get();

        foreach ($outlets as $outlet) {
            OutletProductStock::firstOrCreate(
                [
                    'outlet_id' => $outlet->id,
                    'product_id' => $product->id,
                ],
                [
                    'quantity' => 0,
                    'minimum_stock' => 0,
                ]
            );
        }
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
        if ($user->role === 'owner') {
            return;
        }

        if ($user->role === 'cashier' && (int) $user->outlet_id === (int) $outlet->id) {
            return;
        }

        throw new AuthorizationException('Anda tidak memiliki akses ke outlet ini.');
    }
}
