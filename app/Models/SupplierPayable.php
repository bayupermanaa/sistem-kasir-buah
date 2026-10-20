<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierPayable extends Model
{
    use HasFactory;

    public const STATUSES = ['open', 'partial', 'paid'];

    protected $fillable = [
        'outlet_id', 'supplier_id', 'source_type', 'source_id', 'invoice_number',
        'debt_date', 'due_date', 'original_amount', 'paid_amount', 'outstanding_amount',
        'status', 'description', 'created_by',
    ];

    protected $casts = [
        'debt_date' => 'date',
        'due_date' => 'date',
        'original_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
    ];

    public function outlet(): BelongsTo { return $this->belongsTo(Outlet::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function payments(): HasMany { return $this->hasMany(PayablePayment::class); }
    public function source(): \Illuminate\Database\Eloquent\Relations\MorphTo { return $this->morphTo(); }
}
