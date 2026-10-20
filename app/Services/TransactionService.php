<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockHistory;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Support\DecimalMath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly SingleOutletService $singleOutletService,
        private readonly SalesAccountingService $salesAccountingService,
    ) {
    }

    public function store(User $user, array $data): Transaction
    {
        $fingerprint = $this->buildFingerprint($user, $data);
        $idempotencyKey = filled($data['idempotency_key'] ?? null)
            ? (string) $data['idempotency_key']
            : $fingerprint;
        $cacheKey = 'transaction:'.$user->id.':'.$idempotencyKey;

        if ($this->idempotencyColumnExists() && Transaction::query()->where('idempotency_key', $idempotencyKey)->exists()) {
            throw ValidationException::withMessages([
                'transaction' => 'Transaksi yang sama sudah pernah diproses.',
            ]);
        }

        if (! Cache::add($cacheKey, now()->timestamp, 10)) {
            throw ValidationException::withMessages([
                'transaction' => 'Transaksi yang sama sedang atau baru saja diproses.',
            ]);
        }

        try {
            return DB::transaction(function () use ($user, $data, $idempotencyKey): Transaction {
                $outlet = $this->singleOutletService->getDefaultOutlet();
                $paymentMethod = (string) $data['payment_method'];
                $items = $data['items'] ?? [];

                if ($items === []) {
                    throw ValidationException::withMessages([
                        'items' => 'Minimal satu item transaksi harus diisi.',
                    ]);
                }

                $invoiceNumber = $this->generateInvoiceNumber();
                $preparedItems = [];
                $subtotal = 0;

                foreach ($items as $index => $item) {
                    $product = Product::query()->find($item['product_id']);

                    if (! $product) {
                        throw ValidationException::withMessages([
                            "items.$index.product_id" => 'Produk tidak ditemukan.',
                        ]);
                    }

                    if (! $product->is_active) {
                        throw ValidationException::withMessages([
                            "items.$index.product_id" => "Produk {$product->name} tidak aktif dan tidak dapat ditransaksikan.",
                        ]);
                    }

                    $quantity = $this->resolveQuantity($item);
                    $weightGrams = array_key_exists('weight_grams', $item) && $item['weight_grams'] !== null
                        ? (int) $item['weight_grams']
                        : null;
                    $pricePerUnit = DecimalMath::parse($product->selling_price, 2);
                    $lineSubtotal = DecimalMath::multiply($quantity, $pricePerUnit, 1000);
                    $stock = $this->stockService->lockStock($outlet->id, $product->id);

                    $stockQuantity = DecimalMath::parse($stock->quantity, 3);
                    if ($stockQuantity < $quantity) {
                        throw ValidationException::withMessages([
                            "items.$index.product_id" => "Stok {$product->name} tidak cukup.",
                        ]);
                    }

                    $preparedItems[] = [
                        'product' => $product,
                        'quantity' => $quantity,
                        'weight_grams' => $weightGrams,
                        'price_per_unit' => $pricePerUnit,
                        'subtotal' => $lineSubtotal,
                        'stock' => $stock,
                    ];

                    $subtotal += $lineSubtotal;
                }

                $subtotal = $subtotal;
                $discount = $this->resolveDiscountAmount($data, $subtotal);

                if ($discount > $subtotal) {
                    throw ValidationException::withMessages([
                        'discount' => 'Diskon tidak boleh lebih besar dari subtotal.',
                    ]);
                }

                $total = $subtotal - $discount;
                $cashReceived = null;
                $changeAmount = null;

                if ($paymentMethod === 'cash') {
                    $cashReceived = $this->normalizeMoney($data['cash_received'] ?? null);

                    if ($cashReceived === null) {
                        throw ValidationException::withMessages([
                            'cash_received' => 'Uang tunai yang diterima wajib diisi.',
                        ]);
                    }

                    if ($cashReceived < $total) {
                        throw ValidationException::withMessages([
                            'cash_received' => 'Uang tunai tidak mencukupi.',
                        ]);
                    }

                    $changeAmount = $cashReceived - $total;
                }

                $transactionAttributes = [
                    'invoice_number' => $invoiceNumber,
                    'outlet_id' => $outlet->id,
                    'user_id' => $user->id,
                    'subtotal' => DecimalMath::format($subtotal, 2),
                    'discount' => DecimalMath::format($discount, 2),
                    'total' => DecimalMath::format($total, 2),
                    'payment_method' => $paymentMethod,
                    'cash_received' => $cashReceived === null ? null : DecimalMath::format($cashReceived, 2),
                    'change_amount' => $changeAmount === null ? null : DecimalMath::format($changeAmount, 2),
                    'status' => 'completed',
                ];
                if ($this->idempotencyColumnExists()) {
                    $transactionAttributes['idempotency_key'] = $idempotencyKey;
                }
                $transaction = Transaction::create($transactionAttributes);

                foreach ($preparedItems as $preparedItem) {
                    $stock = $this->stockService->lockStock($outlet->id, $preparedItem['product']->id);
                    $quantityBefore = DecimalMath::parse($stock->quantity, 3);
                    $quantityChange = -$preparedItem['quantity'];
                    $quantityAfter = $quantityBefore + $quantityChange;

                    if ($quantityAfter < 0) {
                        throw ValidationException::withMessages([
                            'items' => 'Stok tidak boleh negatif.',
                        ]);
                    }

                    $stock->quantity = DecimalMath::format($quantityAfter, 3);
                    $stock->save();

                    TransactionItem::create([
                        'transaction_id' => $transaction->id,
                        'product_id' => $preparedItem['product']->id,
                        'quantity' => DecimalMath::format($preparedItem['quantity'], 3),
                        'weight_grams' => $preparedItem['weight_grams'],
                        'price_per_unit' => DecimalMath::format($preparedItem['price_per_unit'], 2),
                        'subtotal' => DecimalMath::format($preparedItem['subtotal'], 2),
                    ]);

                    StockHistory::create([
                        'outlet_id' => $outlet->id,
                        'product_id' => $preparedItem['product']->id,
                        'user_id' => $user->id,
                        'reference_type' => Transaction::class,
                        'reference_id' => $transaction->id,
                        'type' => 'sale',
                        'quantity_before' => DecimalMath::format($quantityBefore, 3),
                        'quantity_change' => DecimalMath::format($quantityChange, 3),
                        'quantity_after' => DecimalMath::format($quantityAfter, 3),
                        'reason' => 'Penjualan',
                        'notes' => null,
                    ]);
                }

                $this->salesAccountingService->recordSale($transaction);

                return $transaction->load(['outlet', 'user', 'items.product']);
            });
        } catch (\Throwable $throwable) {
            Cache::forget($cacheKey);

            throw $throwable;
        }
    }

    private function generateInvoiceNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "TRX-{$date}-";

        $latest = Transaction::query()
            ->where('invoice_number', 'like', "{$prefix}%")
            ->orderByDesc('invoice_number')
            ->lockForUpdate()
            ->first();

        $sequence = 1;

        if ($latest && preg_match('/^TRX-\d{8}-(\d{4})$/', $latest->invoice_number, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function resolveQuantity(array $item): int
    {
        if (array_key_exists('weight_grams', $item) && $item['weight_grams'] !== null) {
            $grams = (int) $item['weight_grams'];
            if ($grams <= 0) {
                throw ValidationException::withMessages(['items' => 'Berat item harus lebih besar dari 0 gram.']);
            }

            return $grams;
        }

        if (array_key_exists('quantity', $item) && $item['quantity'] !== null) {
            try {
                $quantity = DecimalMath::parse($item['quantity'], 3);
            } catch (\InvalidArgumentException) {
                throw ValidationException::withMessages(['items' => 'Quantity item tidak valid.']);
            }
            if ($quantity <= 0) {
                throw ValidationException::withMessages(['items' => 'Quantity item harus lebih besar dari 0.']);
            }

            return $quantity;
        }

        throw ValidationException::withMessages([
            'items' => 'Setiap item harus memiliki quantity atau weight_grams.',
        ]);
    }

    private function normalizeMoney(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return max(0, DecimalMath::parse($value, 2));
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages(['amount' => 'Nominal tidak valid.']);
        }
    }

    private function resolveDiscountAmount(array $data, int $subtotal): int
    {
        $discountType = $data['discount_type'] ?? null;

        if ($discountType === null || $discountType === '') {
            return $this->normalizeMoney($data['discount'] ?? 0) ?? 0;
        }

        $discountValue = $this->normalizeMoney($data['discount_value'] ?? 0) ?? 0;

        return match ($discountType) {
            'amount' => $discountValue,
            'percent' => DecimalMath::multiply($subtotal, $discountValue, 10000),
            default => 0,
        };
    }

    private function buildFingerprint(User $user, array $data): string
    {
        $cashReceived = $this->normalizeMoney($data['cash_received'] ?? null);
        $discount = $this->normalizeMoney($data['discount'] ?? 0);
        $paymentMethod = (string) ($data['payment_method'] ?? '');
        $discountType = (string) ($data['discount_type'] ?? '');
        $discountValue = $this->normalizeMoney($data['discount_value'] ?? 0);
            $items = collect($data['items'] ?? [])
                ->map(function (array $item): array {
                    return [
                        'product_id' => (int) ($item['product_id'] ?? 0),
                        'weight_grams' => isset($item['weight_grams']) ? (int) $item['weight_grams'] : null,
                        'quantity' => isset($item['quantity']) ? $this->quantityFingerprint($item['quantity']) : null,
                    ];
                })
            ->sortBy(function (array $item): string {
                return sprintf(
                    '%010d|%010d|%016s',
                    $item['product_id'],
                    $item['weight_grams'] ?? 0,
                    (string) ($item['quantity'] ?? '0.000')
                );
            })
            ->values()
            ->all();

        return 'transaction:'.$user->id.':'.md5(json_encode([
            'payment_method' => $paymentMethod,
            'cash_received' => $cashReceived,
            'discount' => $discount,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'items' => $items,
        ]));
    }

    private function idempotencyColumnExists(): bool
    {
        return Schema::hasColumn('transactions', 'idempotency_key');
    }

    private function quantityFingerprint(mixed $value): string
    {
        try {
            return DecimalMath::format(DecimalMath::parse($value, 3), 3);
        } catch (\InvalidArgumentException) {
            return '0.000';
        }
    }
}
