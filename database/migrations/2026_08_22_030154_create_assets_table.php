<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('outlet_id')->constrained()->restrictOnDelete();
            $table->string('asset_code')->unique();
            $table->string('name');
            $table->string('category');
            $table->date('purchase_date');
            $table->decimal('purchase_price', 15, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('total_cost', 15, 2);
            $table->string('payment_method', 20)->nullable();
            $table->unsignedSmallInteger('useful_life_years')->nullable();
            $table->decimal('residual_value', 15, 2)->default(0);
            $table->string('status');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['outlet_id', 'purchase_date']);
            $table->index('category');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
