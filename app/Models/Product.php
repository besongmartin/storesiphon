<?php

namespace App\Models;

use App\Domain\Extraction\Data\ProductData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'tags' => 'array',
            'brands' => 'array',
            'attributes' => 'array',
            'variation_attributes' => 'array',
            'images' => 'array',
            'warnings' => 'array',
            'rating_average' => 'float',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ExtractionJob::class, 'extraction_job_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function variations(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function isOnSale(): bool
    {
        return $this->sale_price !== null;
    }

    /** Column values for one ProductData row (bulk insert). */
    public static function rowFrom(ProductData $p, string $jobId, int $position, ?int $parentId = null): array
    {
        $now = now();

        return [
            'extraction_job_id' => $jobId,
            'parent_id' => $parentId,
            'position' => $position,
            'external_id' => mb_substr($p->externalId, 0, 64),
            'type' => $p->type,
            'name' => mb_substr($p->name, 0, 500),
            'slug' => $p->slug !== null ? mb_substr($p->slug, 0, 300) : null,
            'sku' => $p->sku !== null ? mb_substr($p->sku, 0, 191) : null,
            'permalink' => $p->permalink,
            'regular_price' => $p->regularPrice,
            'sale_price' => $p->salePrice,
            'price' => $p->price,
            'price_min' => $p->priceMin,
            'price_max' => $p->priceMax,
            'currency' => $p->currency !== null ? strtoupper(substr($p->currency, 0, 3)) : null,
            'stock_status' => $p->stockStatus,
            'stock_quantity' => $p->stockQuantity,
            'categories' => json_encode($p->categories, JSON_UNESCAPED_UNICODE),
            'tags' => json_encode($p->tags, JSON_UNESCAPED_UNICODE),
            'brands' => json_encode($p->brands, JSON_UNESCAPED_UNICODE),
            'attributes' => json_encode($p->attributes, JSON_UNESCAPED_UNICODE),
            'variation_attributes' => json_encode($p->variationAttributes ?: null, JSON_UNESCAPED_UNICODE),
            'images' => json_encode($p->images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'short_description_html' => $p->shortDescriptionHtml,
            'description_html' => $p->descriptionHtml,
            'description_text' => $p->descriptionText,
            'rating_average' => $p->ratingAverage,
            'review_count' => $p->reviewCount,
            'method' => $p->method,
            'warnings' => json_encode($p->warnings ?: null),
            'content_hash' => $p->contentHash(),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
