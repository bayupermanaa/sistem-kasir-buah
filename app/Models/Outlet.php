<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Outlet extends Model
{
    use HasFactory;

    public const DEFAULT_NAME = 'Toko Buah Teras Fatimah';

    protected $fillable = [
        'name',
        'address',
        'phone',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function outletProductStocks(): HasMany
    {
        return $this->hasMany(OutletProductStock::class);
    }

    public function stockHistories(): HasMany
    {
        return $this->hasMany(StockHistory::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function stockOpnames(): HasMany
    {
        return $this->hasMany(StockOpname::class);
    }

    public function stockPurchases(): HasMany
    {
        return $this->hasMany(StockPurchase::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public static function default(): self
    {
        return static::query()
            ->orderByRaw('CASE WHEN name = ? THEN 0 ELSE 1 END, id ASC', [self::DEFAULT_NAME])
            ->firstOrFail();
    }

    public static function defaultId(): int
    {
        return (int) static::default()->id;
    }
}
