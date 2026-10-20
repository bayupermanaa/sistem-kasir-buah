<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->string('payment_method', 20)->nullable()->after('amount');
            $table->index(['outlet_id', 'payment_method']);
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropIndex(['outlet_id', 'payment_method']);
            $table->dropColumn('payment_method');
        });
    }
};
