<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Expense extends Model
{
    use HasFactory;

    public const CATEGORIES = [
        'Plastik',
        'Kemasan',
        'Kotak Hampers',
        'Pita',
        'Listrik',
        'Air',
        'Transportasi',
        'Gaji',
        'Sewa',
        'Perlengkapan',
        'Perawatan',
        'Gas',
        'Internet',
        'Kebersihan',
        'Operasional',
        'Lainnya',
    ];

    protected $fillable = [
        'outlet_id',
        'expense_date',
        'category',
        'description',
        'amount',
        'payment_method',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public static function categories(): array
    {
        return self::CATEGORIES;
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForOutlet(Builder $query, int $outletId): Builder
    {
        return $query->where('outlet_id', $outletId);
    }
}
