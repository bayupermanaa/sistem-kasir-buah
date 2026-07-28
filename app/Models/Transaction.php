<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasFactory;

    public const PAYMENT_METHOD_LABELS = [
        'cash' => 'Tunai',
        'qris' => 'QRIS',
    ];

    public const STATUS_LABELS = [
        'completed' => 'Selesai',
        'pending' => 'Menunggu',
        'cancelled' => 'Dibatalkan',
    ];

    protected $fillable = [
        'invoice_number',
        'outlet_id',
        'user_id',
        'subtotal',
        'discount',
        'total',
        'payment_method',
        'cash_received',
        'change_amount',
        'status',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'cash_received' => 'decimal:2',
        'change_amount' => 'decimal:2',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return self::PAYMENT_METHOD_LABELS[$this->payment_method] ?? strtoupper((string) $this->payment_method);
    }

    public function getPaymentMethodBadgeClassAttribute(): string
    {
        return match ($this->payment_method) {
            'cash' => 'bg-success',
            'qris' => 'bg-primary',
            default => 'bg-secondary',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'completed' => 'bg-success',
            'pending' => 'bg-warning text-dark',
            'cancelled' => 'bg-danger',
            default => 'bg-secondary',
        };
    }

    public function getFormattedCreatedAtAttribute(): string
    {
        return $this->created_at?->translatedFormat('d/m/Y H:i') ?? '-';
    }
}
