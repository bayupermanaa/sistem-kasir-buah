<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutletProductStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'outlet_id',
        'product_id',
        'quantity',
        'minimum_stock',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'minimum_stock' => 'decimal:3',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isOut(): bool
    {
        return (float) $this->quantity <= 0;
    }

    public function isLow(): bool
    {
        return (float) $this->quantity > 0 && (float) $this->quantity <= (float) $this->minimum_stock;
    }

    public function statusLabel(): string
    {
        if ($this->isOut()) {
            return 'Habis';
        }

        if ($this->isLow()) {
            return 'Menipis';
        }

        return 'Aman';
    }

    public function statusBadgeClass(): string
    {
        if ($this->isOut()) {
            return 'text-bg-danger';
        }

        if ($this->isLow()) {
            return 'text-bg-warning';
        }

        return 'text-bg-success';
    }
}
