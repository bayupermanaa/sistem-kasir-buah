<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockHistory extends Model
{
    use HasFactory;
    public const TYPE_GROUPS = [
        'incoming' => [
            'label' => 'Stok Masuk',
            'types' => ['initial_stock', 'restock', 'adjustment_in', 'return_in', 'transfer_in', 'purchase', 'stock_in'],
        ],
        'outgoing' => [
            'label' => 'Stok Keluar',
            'types' => ['sale', 'adjustment_out', 'damaged', 'processed', 'return_out', 'transfer_out', 'transaction', 'stock_out'],
        ],
        'adjustment' => [
            'label' => 'Penyesuaian',
            'types' => ['adjustment', 'adjustment_in', 'adjustment_out', 'damaged', 'processed'],
        ],
        'stock_opname' => [
            'label' => 'Stock Opname',
            'types' => ['stock_opname'],
        ],
    ];

    public const TYPE_META = [
        'restock' => [
            'label' => 'Stok Masuk',
            'icon' => 'bi-box-arrow-in-down',
            'badge' => 'bg-success',
        ],
        'adjustment_in' => [
            'label' => 'Koreksi Tambah',
            'icon' => 'bi-plus-circle',
            'badge' => 'bg-primary',
        ],
        'adjustment_out' => [
            'label' => 'Koreksi Kurang',
            'icon' => 'bi-dash-circle',
            'badge' => 'bg-warning text-dark',
        ],
        'damaged' => [
            'label' => 'Buah Rusak',
            'icon' => 'bi-exclamation-triangle',
            'badge' => 'bg-danger',
        ],
        'processed' => [
            'label' => 'Buah Diproses',
            'icon' => 'bi-scissors',
            'badge' => 'bg-purple',
        ],
        'return_in' => [
            'label' => 'Retur Masuk',
            'icon' => 'bi-arrow-return-left',
            'badge' => 'bg-info text-dark',
        ],
        'return_out' => [
            'label' => 'Retur Keluar',
            'icon' => 'bi-arrow-return-right',
            'badge' => 'bg-secondary',
        ],
        'initial_stock' => [
            'label' => 'Stok Awal',
            'icon' => 'bi-box-seam',
            'badge' => 'bg-dark',
        ],
        'sale' => [
            'label' => 'Penjualan',
            'icon' => 'bi-cart-check',
            'badge' => 'bg-success',
        ],
        'stock_opname' => [
            'label' => 'Stock Opname',
            'icon' => 'bi-clipboard-check',
            'badge' => 'bg-primary',
        ],
        'transfer_in' => [
            'label' => 'Transfer Masuk',
            'icon' => 'bi-arrow-down-left-circle',
            'badge' => 'bg-info text-dark',
        ],
        'transfer_out' => [
            'label' => 'Transfer Keluar',
            'icon' => 'bi-arrow-up-right-circle',
            'badge' => 'bg-secondary',
        ],
    ];

    protected $fillable = [
        'outlet_id',
        'product_id',
        'user_id',
        'reference_type',
        'reference_id',
        'type',
        'quantity_before',
        'quantity_change',
        'quantity_after',
        'reason',
        'notes',
    ];

    protected $casts = [
        'quantity_before' => 'decimal:3',
        'quantity_change' => 'decimal:3',
        'quantity_after' => 'decimal:3',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_META[$this->type]['label'] ?? 'Jenis Tidak Dikenal';
    }

    public function getTypeBadgeClassAttribute(): string
    {
        return self::TYPE_META[$this->type]['badge'] ?? 'bg-secondary';
    }

    public function getTypeIconAttribute(): string
    {
        return self::TYPE_META[$this->type]['icon'] ?? 'bi-question-circle';
    }

    public function getQuantityChangeClassAttribute(): string
    {
        $change = (float) $this->quantity_change;

        return match (true) {
            $change > 0 => 'text-success',
            $change < 0 => 'text-danger',
            default => 'text-muted',
        };
    }

    public function getFormattedQuantityChangeAttribute(): string
    {
        $change = (float) $this->quantity_change;

        return match (true) {
            $change > 0 => '+'.number_format($change, 3, ',', '.'),
            $change < 0 => number_format($change, 3, ',', '.'),
            default => number_format(0, 3, ',', '.'),
        };
    }
}
