<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockHistory;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockOpnameService
{
    public function __construct(
        private readonly SingleOutletService $singleOutletService,
        private readonly StockService $stockService
    ) {
    }

    public function store(User $user, array $data): StockOpname
    {
        $fingerprint = $this->buildFingerprint($user, $data);

        if (! Cache::add($fingerprint, now()->timestamp, 10)) {
            throw ValidationException::withMessages([
                'stock_opname' => 'Stock Opname yang sama baru saja diproses.',
            ]);
        }

        try {
            return DB::transaction(function () use ($user, $data): StockOpname {
                $outlet = $this->singleOutletService->getDefaultOutlet();
                $items = collect($data['items'] ?? [])->filter(fn (array $item): bool => isset($item['product_id']))->values();

                if ($items->isEmpty()) {
                    throw ValidationException::withMessages([
                        'items' => 'Minimal satu produk harus diisi.',
                    ]);
                }

                $opname = StockOpname::create([
                    'outlet_id' => $outlet->id,
                    'opname_date' => Carbon::parse($data['opname_date'])->toDateString(),
                    'created_by' => $user->id,
                    'notes' => $data['notes'] ?? null,
                    'status' => 'completed',
                ]);

                foreach ($items as $index => $item) {
                    $product = Product::query()
                        ->active()
                        ->whereKey($item['product_id'])
                        ->first();

                    if (! $product) {
                        throw ValidationException::withMessages([
                            "items.$index.product_id" => 'Produk tidak aktif atau tidak ditemukan.',
                        ]);
                    }

                    $physicalQuantity = round((float) ($item['physical_quantity'] ?? 0), 3);

                    if ($physicalQuantity < 0) {
                        throw ValidationException::withMessages([
                            "items.$index.physical_quantity" => 'Stok fisik tidak boleh negatif.',
                        ]);
                    }

                    $stock = $this->stockService->lockStock($outlet->id, $product->id);
                    $systemQuantity = round((float) $stock->quantity, 3);
                    $difference = round($physicalQuantity - $systemQuantity, 3);
                    $notes = isset($item['notes']) && trim((string) $item['notes']) !== ''
                        ? trim((string) $item['notes'])
                        : null;

                    StockOpnameItem::create([
                        'stock_opname_id' => $opname->id,
                        'product_id' => $product->id,
                        'system_quantity' => $systemQuantity,
                        'physical_quantity' => $physicalQuantity,
                        'difference' => $difference,
                        'notes' => $notes,
                    ]);

                    if ($difference === 0.0) {
                        continue;
                    }

                    $stock->quantity = $physicalQuantity;
                    $stock->save();

                    StockHistory::create([
                        'outlet_id' => $outlet->id,
                        'product_id' => $product->id,
                        'user_id' => $user->id,
                        'reference_type' => StockOpname::class,
                        'reference_id' => $opname->id,
                        'type' => 'stock_opname',
                        'quantity_before' => $systemQuantity,
                        'quantity_change' => $difference,
                        'quantity_after' => $physicalQuantity,
                        'reason' => 'Stock Opname',
                        'notes' => $notes,
                    ]);
                }

                return $opname->load([
                    'outlet',
                    'creator:id,name',
                    'items.product.unit',
                    'items.product.category',
                ]);
            });
        } catch (\Throwable $throwable) {
            Cache::forget($fingerprint);

            throw $throwable;
        }
    }

    private function buildFingerprint(User $user, array $data): string
    {
        $items = collect($data['items'] ?? [])
            ->map(function (array $item): array {
                return [
                    'product_id' => (int) ($item['product_id'] ?? 0),
                    'physical_quantity' => round((float) ($item['physical_quantity'] ?? 0), 3),
                    'notes' => trim((string) ($item['notes'] ?? '')),
                ];
            })
            ->sortBy(fn (array $item): string => sprintf(
                '%010d|%015s|%s',
                $item['product_id'],
                number_format($item['physical_quantity'], 3, '.', ''),
                $item['notes']
            ))
            ->values()
            ->all();

        return 'stock-opname:'.$user->id.':'.md5(json_encode([
            'opname_date' => (string) ($data['opname_date'] ?? ''),
            'notes' => (string) ($data['notes'] ?? ''),
            'items' => $items,
        ]));
    }
}
