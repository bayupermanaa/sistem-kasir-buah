<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockHistory;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly SingleOutletService $singleOutletService
    ) {
    }

    public function store(User $user, array $data): Transaction
    {
        $fingerprint = $this->buildFingerprint($user, $data);

        if (! Cache::add($fingerprint, now()->timestamp, 10)) {
            throw ValidationException::withMessages([
                'transaction' => 'Transaksi yang sama sedang atau baru saja diproses.',
            ]);
        }

        try {
            return DB::transaction(function () use ($user, $data): Transaction {
                $outlet = $this->singleOutletService->getDefaultOutlet();
                $paymentMethod = (string) $data['payment_method'];
                $discount = $this->normalizeMoney($data['discount'] ?? 0);
                $items = $data['items'] ?? [];

                if ($items === []) {
                    throw ValidationException::withMessages([
                        'items' => 'Minimal satu item transaksi harus diisi.',
                    ]);
                }

                $invoiceNumber = $this->generateInvoiceNumber();
                $preparedItems = [];
                $subtotal = 0.0;

                foreach ($items as $index => $item) {
                    $product = Product::query()->findOrFail($item['product_id']);
                    $quantity = $this->resolveQuantity($item);
                    $weightGrams = array_key_exists('weight_grams', $item) && $item['weight_grams'] !== null
                        ? (int) $item['weight_grams']
                        : null;
                    $pricePerUnit = $this->normalizeMoney($product->selling_price);
                    $lineSubtotal = round($quantity * $pricePerUnit, 2);
                    $stock = $this->stockService->lockStock($outlet->id, $product->id);

                    if (round((float) $stock->quantity, 3) < $quantity) {
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

                $subtotal = round($subtotal, 2);

                if ($discount > $subtotal) {
                    throw ValidationException::withMessages([
                        'discount' => 'Diskon tidak boleh lebih besar dari subtotal.',
                    ]);
                }

                $total = round($subtotal - $discount, 2);
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

                    $changeAmount = round($cashReceived - $total, 2);
                }

                $transaction = Transaction::create([
                    'invoice_number' => $invoiceNumber,
                    'outlet_id' => $outlet->id,
                    'user_id' => $user->id,
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'total' => $total,
                    'payment_method' => $paymentMethod,
                    'cash_received' => $cashReceived,
                    'change_amount' => $changeAmount,
                    'status' => 'completed',
                ]);

                foreach ($preparedItems as $preparedItem) {
                    $stock = $preparedItem['stock'];
                    $quantityBefore = round((float) $stock->quantity, 3);
                    $quantityChange = round(-$preparedItem['quantity'], 3);
                    $quantityAfter = round($quantityBefore + $quantityChange, 3);

                    if ($quantityAfter < 0) {
                        throw ValidationException::withMessages([
                            'items' => 'Stok tidak boleh negatif.',
                        ]);
                    }

                    $stock->quantity = $quantityAfter;
                    $stock->save();

                    TransactionItem::create([
                        'transaction_id' => $transaction->id,
                        'product_id' => $preparedItem['product']->id,
                        'quantity' => $preparedItem['quantity'],
                        'weight_grams' => $preparedItem['weight_grams'],
                        'price_per_unit' => $preparedItem['price_per_unit'],
                        'subtotal' => $preparedItem['subtotal'],
                    ]);

                    StockHistory::create([
                        'outlet_id' => $outlet->id,
                        'product_id' => $preparedItem['product']->id,
                        'user_id' => $user->id,
                        'reference_type' => Transaction::class,
                        'reference_id' => $transaction->id,
                        'type' => 'sale',
                        'quantity_before' => $quantityBefore,
                        'quantity_change' => $quantityChange,
                        'quantity_after' => $quantityAfter,
                        'reason' => 'Penjualan',
                        'notes' => null,
                    ]);
                }

                return $transaction->load(['outlet', 'user', 'items.product']);
            });
        } catch (\Throwable $throwable) {
            Cache::forget($fingerprint);

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

    private function resolveQuantity(array $item): float
    {
        if (array_key_exists('weight_grams', $item) && $item['weight_grams'] !== null) {
            return round(((int) $item['weight_grams']) / 1000, 3);
        }

        if (array_key_exists('quantity', $item) && $item['quantity'] !== null) {
            return round((float) $item['quantity'], 3);
        }

        throw ValidationException::withMessages([
            'items' => 'Setiap item harus memiliki quantity atau weight_grams.',
        ]);
    }

    private function normalizeMoney(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    private function buildFingerprint(User $user, array $data): string
    {
        $cashReceived = $this->normalizeMoney($data['cash_received'] ?? null);
        $discount = $this->normalizeMoney($data['discount'] ?? 0);
        $paymentMethod = (string) ($data['payment_method'] ?? '');
            $items = collect($data['items'] ?? [])
                ->map(function (array $item): array {
                    return [
                        'product_id' => (int) ($item['product_id'] ?? 0),
                        'weight_grams' => isset($item['weight_grams']) ? (int) $item['weight_grams'] : null,
                        'quantity' => isset($item['quantity']) ? (float) $item['quantity'] : null,
                    ];
                })
            ->sortBy(function (array $item): string {
                return sprintf(
                    '%010d|%010d|%016s',
                    $item['product_id'],
                    $item['weight_grams'] ?? 0,
                    number_format((float) ($item['quantity'] ?? 0), 3, '.', '')
                );
            })
            ->values()
            ->all();

        return 'transaction:'.$user->id.':'.md5(json_encode([
            'payment_method' => $paymentMethod,
            'cash_received' => $cashReceived,
            'discount' => $discount,
            'items' => $items,
        ]));
    }
}
