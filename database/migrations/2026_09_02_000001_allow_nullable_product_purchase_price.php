<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE products MODIFY purchase_price DECIMAL(12,2) NULL DEFAULT NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('products')->whereNull('purchase_price')->update(['purchase_price' => 0]);
            DB::statement('ALTER TABLE products MODIFY purchase_price DECIMAL(12,2) NOT NULL DEFAULT 0');
        }
    }
};