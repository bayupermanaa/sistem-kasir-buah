<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayablePayment extends Model
{
    use HasFactory;

    public const PAYMENT_METHODS = [
        'cash' => 'Tunai',
        'qris' => 'QRIS/Bank',
        'transfer' => 'Transfer',
        'other' => 'Lainnya',
    ];

    protected $fillable = [
        'supplier_payable_id', 'outlet_id', 'payment_date', 'amount', 'payment_method',
        'idempotency_key', 'reference_number', 'notes', 'created_by',
    ];

    protected $casts = ['payment_date' => 'date', 'amount' => 'decimal:2'];

    public function payable(): BelongsTo { return $this->belongsTo(SupplierPayable::class, 'supplier_payable_id'); }
    public function outlet(): BelongsTo { return $this->belongsTo(Outlet::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
