<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingAccount extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name', 'type', 'subtype', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }
}
