<?php

namespace App\Console\Commands;

use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Console\Command;

class SyncSingleOutletMode extends Command
{
    protected $signature = 'single-outlet:sync';

    protected $description = 'Synchronize legacy data to the single outlet mode';

    public function handle(StockService $stockService): int
    {
        $outlet = Outlet::updateOrCreate([
            'name' => Outlet::DEFAULT_NAME,
        ], [
            'address' => null,
            'phone' => null,
        ]);

        User::query()
            ->whereIn('role', ['owner', 'cashier'])
            ->update(['outlet_id' => $outlet->id]);

        Product::query()->orderBy('id')->chunkById(100, function ($products) use ($stockService): void {
            foreach ($products as $product) {
                $stockService->initializeProductStocks($product);
            }
        });

        $this->info('Single outlet mode synchronized successfully.');

        return self::SUCCESS;
    }
}
