<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\OutletProductStock;
use App\Models\Product;
use App\Models\StockHistory;
use App\Models\StockPurchase;
use App\Models\User;
use App\Support\DecimalMath;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    public function __construct(
        private readonly SingleOutletService $singleOutletService,
        private readonly SupplierPayableService $supplierPayableService,
    )
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
        mixed $quantity,
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
            $quantityValue = $this->positiveQuantity($quantity);
            $quantityBefore = DecimalMath::parse($stock->quantity, 3);
            $change = $this->changeValue($type, $quantityValue);
            $quantityAfter = $quantityBefore + $change;

            if ($quantityAfter < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Stok tidak boleh negatif.',
                ]);
            }

            $stock->quantity = DecimalMath::format($quantityAfter, 3);
            $stock->save();

            return StockHistory::create([
                'outlet_id' => $outlet->id,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'type' => $type,
                'quantity_before' => DecimalMath::format($quantityBefore, 3),
                'quantity_change' => DecimalMath::format($change, 3),
                'quantity_after' => DecimalMath::format($quantityAfter, 3),
                'reason' => $reason,
                'notes' => $notes,
            ]);
        });
    }

    public function initializeStock(
        User $user,
        Outlet $outlet,
        Product $product,
        mixed $quantity,
        mixed $minimumStock,
        ?string $reason = 'Initial stock',
        ?string $notes = null
    ): StockHistory {
        return DB::transaction(function () use ($user, $outlet, $product, $quantity, $minimumStock, $reason, $notes): StockHistory {
            $this->assertUserCanAccessOutlet($user, $outlet);

            $stock = $this->lockStock($outlet->id, $product->id);
            $quantityValue = $this->nonNegativeQuantity($quantity);
            $minimumValue = $this->nonNegativeQuantity($minimumStock);
            $quantityBefore = DecimalMath::parse($stock->quantity, 3);
            $stock->minimum_stock = DecimalMath::format($minimumValue, 3);
            $stock->quantity = DecimalMath::format($quantityValue, 3);
            $stock->save();

            return StockHistory::create([
                'outlet_id' => $outlet->id,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'reference_type' => null,
                'reference_id' => null,
                'type' => 'initial_stock',
                'quantity_before' => DecimalMath::format($quantityBefore, 3),
                'quantity_change' => DecimalMath::format($quantityValue - $quantityBefore, 3),
                'quantity_after' => DecimalMath::format($quantityValue, 3),
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

    public function purchaseStock(
        User $user,
        Outlet $outlet,
        Product $product,
        mixed $quantity,
        mixed $unitCost,
        string $purchaseDate,
        ?string $paymentMethod,
        ?string $notes = null,
        ?int $supplierId = null,
        ?string $invoiceNumber = null,
        ?string $dueDate = null,
    ): StockPurchase
    {
        $this->assertUserCanAccessOutlet($user, $outlet);

        return DB::transaction(function () use ($user, $outlet, $product, $quantity, $unitCost, $purchaseDate, $paymentMethod, $notes, $supplierId, $invoiceNumber, $dueDate): StockPurchase {
            $accountingActive = $this->supplierPayableService->hasActiveAccounting($outlet->id);
            if ($accountingActive && ! in_array($paymentMethod, ['cash', 'qris', 'transfer', 'credit'], true)) {
                throw ValidationException::withMessages(['payment_method' => 'Metode pembayaran wajib dipilih saat pembukuan aktif.']);
            }
            if ($accountingActive && $purchaseDate < $this->supplierPayableService->cutoverDate($outlet->id)?->toDateString()) {
                throw ValidationException::withMessages(['purchase_date' => 'Pembelian tidak boleh dicatat sebelum tanggal cutover.']);
            }
            if ($paymentMethod === 'credit' && (! $supplierId || ! $dueDate)) {
                throw ValidationException::withMessages(['supplier_id' => 'Supplier dan tanggal jatuh tempo wajib untuk pembelian kredit.']);
            }
            if ($paymentMethod === 'credit' && ! $accountingActive) {
                throw ValidationException::withMessages(['accounting' => 'Pembukuan belum aktif. Pembelian kredit belum dapat digunakan.']);
            }

            $stock = $this->lockStock($outlet->id, $product->id);
            $quantityValue = $this->positiveQuantity($quantity);
            $unitCostValue = $unitCost === null ? null : $this->positiveMoney($unitCost);
            $before = DecimalMath::parse($stock->quantity, 3);
            $after = $before + $quantityValue;
            $stock->quantity = DecimalMath::format($after, 3);
            $stock->save();

            $purchase = StockPurchase::create([
                'outlet_id' => $outlet->id,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'supplier_id' => $supplierId,
                'invoice_number' => $invoiceNumber,
                'quantity' => DecimalMath::format($quantityValue, 3),
                'unit_cost' => $unitCostValue === null ? null : DecimalMath::format($unitCostValue, 2),
                'total_cost' => $unitCostValue === null ? null : DecimalMath::format(DecimalMath::multiply($quantityValue, $unitCostValue, 1000), 2),
                'purchase_date' => $purchaseDate,
                'due_date' => $dueDate,
                'payment_method' => $paymentMethod,
                'notes' => $notes,
            ]);

            if ($accountingActive) {
                if ($paymentMethod === 'credit') {
                    $this->supplierPayableService->createFromStockPurchase($user, $purchase, $supplierId, $dueDate);
                    $this->supplierPayableService->recordStockPurchaseJournal($user, $purchase, 'payable');
                } else {
                    $this->supplierPayableService->recordStockPurchaseJournal(
                        $user,
                        $purchase,
                        $paymentMethod === 'cash' ? 'cash' : 'bank'
                    );
                }
            }

            StockHistory::create([
                'outlet_id' => $outlet->id,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'reference_type' => StockPurchase::class,
                'reference_id' => $purchase->id,
                'type' => 'restock',
                'quantity_before' => DecimalMath::format($before, 3),
                'quantity_change' => DecimalMath::format($quantityValue, 3),
                'quantity_after' => DecimalMath::format($after, 3),
                'reason' => 'Pembelian stok',
                'notes' => $notes,
            ]);

            return $purchase->load(['outlet', 'product', 'user']);
        });
    }
    public function updateMinimumStock(User $user, OutletProductStock $stock, mixed $minimumStock): OutletProductStock
    {
        $this->assertUserCanAccessOutlet($user, $stock->outlet);

        return DB::transaction(function () use ($stock, $minimumStock): OutletProductStock {
            $lockedStock = OutletProductStock::whereKey($stock->id)->lockForUpdate()->firstOrFail();
            $lockedStock->minimum_stock = DecimalMath::format($this->nonNegativeQuantity($minimumStock), 3);
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

    private function changeValue(string $type, int $quantity): int
    {
        return in_array($type, ['adjustment_out', 'damaged', 'processed', 'return_out'], true)
            ? -abs($quantity)
            : abs($quantity);
    }

    private function positiveQuantity(mixed $value): int
    {
        $parsed = $this->nonNegativeQuantity($value);
        if ($parsed <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Jumlah harus lebih besar dari 0.']);
        }

        return $parsed;
    }

    private function nonNegativeQuantity(mixed $value): int
    {
        try {
            $parsed = DecimalMath::parse($value, 3);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages(['quantity' => 'Jumlah harus berupa angka dengan maksimal 3 desimal.']);
        }

        if ($parsed < 0) {
            throw ValidationException::withMessages(['quantity' => 'Jumlah tidak boleh negatif.']);
        }

        return $parsed;
    }

    private function positiveMoney(mixed $value): int
    {
        try {
            $parsed = DecimalMath::parse($value, 2);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages(['unit_cost' => 'Harga beli harus berupa nominal yang valid.']);
        }

        if ($parsed <= 0) {
            throw ValidationException::withMessages(['unit_cost' => 'Harga beli harus lebih besar dari nol.']);
        }

        return $parsed;
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
