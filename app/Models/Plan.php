<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = ['slug', 'name', 'price_usd', 'period_days', 'max_products_per_job', 'product_quota', 'concurrent_jobs', 'retention_days', 'export_formats', 'is_active', 'sort'];

    protected function casts(): array
    {
        return [
            'price_usd' => 'decimal:2',
            'export_formats' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public static function free(): self
    {
        return static::where('slug', 'free')->firstOrFail();
    }

    public function isFree(): bool
    {
        return (float) $this->price_usd <= 0.0;
    }

    /** @return Collection<int, self> */
    public static function purchasable()
    {
        return static::where('is_active', true)->where('price_usd', '>', 0)->orderBy('sort')->get();
    }
}
