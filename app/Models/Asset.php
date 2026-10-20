<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use HasFactory, SoftDeletes;

    public const CATEGORIES = [
        'Peralatan Toko',
        'Elektronik',
        'Furniture',
        'Kendaraan',
        'Mesin/Peralatan Produksi',
        'Lainnya',
    ];

    public const STATUSES = [
        'Aktif',
        'Rusak',
        'Dijual',
        'Tidak Digunakan',
    ];

    public const PAYMENT_METHODS = [
        'cash' => 'Tunai',
        'qris' => 'QRIS',
        'transfer' => 'Transfer',
        'other' => 'Lainnya',
    ];

    protected $fillable = [
        'outlet_id',
        'asset_code',
        'name',
        'category',
        'purchase_date',
        'purchase_price',
        'quantity',
        'total_cost',
        'payment_method',
        'useful_life_years',
        'residual_value',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_price' => 'decimal:2',
        'quantity' => 'integer',
        'total_cost' => 'decimal:2',
        'useful_life_years' => 'integer',
        'residual_value' => 'decimal:2',
    ];

    public static function categories(): array
    {
        return self::CATEGORIES;
    }

    public static function statuses(): array
    {
        return self::STATUSES;
    }

    public static function paymentMethods(): array
    {
        return self::PAYMENT_METHODS;
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'reference_id')
            ->where('reference_type', self::class)
            ->where('journal_type', 'asset_purchase');
    }

    public function scopeForOutlet(Builder $query, int $outletId): Builder
    {
        return $query->where('outlet_id', $outletId);
    }

    public static function generateAutoCode(): string
    {
        $maxSequence = (int) static::query()
            ->withTrashed()
            ->selectRaw('COALESCE(MAX(CAST(SUBSTRING(asset_code, 5) AS UNSIGNED)), 0) as max_sequence')
            ->lockForUpdate()
            ->value('max_sequence');

        return sprintf('AST-%04d', $maxSequence + 1);
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status ?: '-';
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'Aktif' => 'bg-success',
            'Rusak' => 'bg-danger',
            'Dijual' => 'bg-warning text-dark',
            'Tidak Digunakan' => 'bg-secondary',
            default => 'bg-secondary',
        };
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return self::PAYMENT_METHODS[$this->payment_method] ?? '-';
    }

    public function getPaymentMethodBadgeClassAttribute(): string
    {
        return match ($this->payment_method) {
            'cash' => 'bg-success',
            'qris' => 'bg-primary',
            'transfer' => 'bg-info text-dark',
            'other' => 'bg-secondary',
            default => 'bg-secondary',
        };
    }

    public function getFormattedPurchaseDateAttribute(): string
    {
        return $this->purchase_date?->translatedFormat('d/m/Y') ?? '-';
    }

    public function getFormattedPurchasePriceAttribute(): string
    {
        return 'Rp '.number_format((float) $this->purchase_price, 0, ',', '.');
    }

    public function getFormattedTotalCostAttribute(): string
    {
        return 'Rp '.number_format((float) $this->total_cost, 0, ',', '.');
    }
}
