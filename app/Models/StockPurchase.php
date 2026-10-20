<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockPurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'outlet_id', 'product_id', 'user_id', 'supplier_id', 'invoice_number', 'quantity', 'unit_cost', 'total_cost',
        'purchase_date', 'due_date', 'payment_method', 'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'purchase_date' => 'date',
        'due_date' => 'date',
    ];

    public function outlet(): BelongsTo { return $this->belongsTo(Outlet::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function payable(): \Illuminate\Database\Eloquent\Relations\MorphOne { return $this->morphOne(SupplierPayable::class, 'source'); }
}
