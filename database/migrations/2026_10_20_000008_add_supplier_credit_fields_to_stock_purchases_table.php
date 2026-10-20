<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_purchases', function (Blueprint $table): void {
            $table->foreignId('supplier_id')->nullable()->after('user_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('invoice_number')->nullable()->after('supplier_id');
            $table->date('due_date')->nullable()->after('purchase_date');
            $table->index(['supplier_id', 'purchase_date']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_purchases', function (Blueprint $table): void {
            $table->dropForeign(['supplier_id']);
            $table->dropIndex(['supplier_id', 'purchase_date']);
            $table->dropColumn(['supplier_id', 'invoice_number', 'due_date']);
        });
    }
};
