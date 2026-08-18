<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'unit_id',
        'code',
        'name',
        'description',
        'image',
        'purchase_price',
        'selling_price',
        'is_active',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function outletProductStocks(): HasMany
    {
        return $this->hasMany(OutletProductStock::class);
    }

    public function stockHistories(): HasMany
    {
        return $this->hasMany(StockHistory::class);
    }

    public function stockOpnameItems(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    public function transactionItems(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getImageUrlAttribute(): ?string
    {
        $path = $this->normalizedImagePath();

        if (! $path) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $path = ltrim($path, '/');
        $path = preg_replace('#^(storage/|public/)#', '', $path);

        return Storage::disk('public')->url($path);
    }

    public function normalizedImagePath(): ?string
    {
        if (! $this->image) {
            return null;
        }

        $path = trim((string) $this->image);

        if ($path === '') {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#^[A-Za-z]:/.*?/storage/app/public/#', '', $path);
        $path = preg_replace('#^.*/storage/app/public/#', '', $path);
        $path = preg_replace('#^storage/app/public/#', '', $path);
        $path = preg_replace('#^storage/#', '', $path);
        $path = preg_replace('#^public/#', '', $path);

        return ltrim($path, '/');
    }

    public static function generateAutoCode(Category $category): string
    {
        $prefix = self::generateCodePrefix($category);
        $sequence = self::nextCodeSequence($prefix);

        return sprintf('%s-%04d', $prefix, $sequence);
    }

    public static function generateCodePrefix(Category $category): string
    {
        $source = trim((string) ($category->slug ?: $category->name));
        $normalized = preg_replace('/[^A-Za-z0-9\s-]+/', ' ', $source) ?: $source;
        $parts = preg_split('/[\s-]+/', strtolower($normalized), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $prefix = collect($parts)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');

        if (strlen($prefix) < 2) {
            $compact = preg_replace('/[^A-Za-z0-9]+/', '', $normalized) ?: 'PRD';
            $prefix = Str::upper(Str::substr($compact, 0, 4));
        }

        return Str::upper(Str::substr($prefix, 0, 4));
    }

    private static function nextCodeSequence(string $prefix): int
    {
        $latestCode = self::withTrashed()
            ->where('code', 'like', $prefix.'-%')
            ->orderByDesc('code')
            ->lockForUpdate()
            ->value('code');

        if ($latestCode && preg_match('/^'.preg_quote($prefix, '/').'-([0-9]{4})$/', $latestCode, $matches)) {
            return ((int) $matches[1]) + 1;
        }

        return 1;
    }
}
