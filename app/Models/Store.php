<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    protected $fillable = ['host', 'origin', 'name', 'signals', 'last_preflight_at'];

    protected function casts(): array
    {
        return [
            'signals' => 'array',
            'last_preflight_at' => 'datetime',
            'opted_out_at' => 'datetime',
        ];
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(ExtractionJob::class);
    }

    public function isOptedOut(): bool
    {
        return $this->opted_out_at !== null;
    }

    /** Store hosts are compared without a leading www. */
    public static function normalizeHost(string $host): string
    {
        return preg_replace('/^www\./', '', strtolower($host));
    }
}
