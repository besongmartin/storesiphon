<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One extraction run. Lifecycle:
 * queued → preflight → running → succeeded | partial | failed | blocked | cancelled
 */
class ExtractionJob extends Model
{
    use HasUlids;

    public const QUEUED = 'queued';

    public const PREFLIGHT = 'preflight';

    public const RUNNING = 'running';

    public const SUCCEEDED = 'succeeded';

    public const PARTIAL = 'partial';

    public const FAILED = 'failed';

    public const BLOCKED = 'blocked';

    public const CANCELLED = 'cancelled';

    public const ACTIVE = [self::QUEUED, self::PREFLIGHT, self::RUNNING];

    public const FINISHED = [self::SUCCEEDED, self::PARTIAL, self::FAILED, self::BLOCKED, self::CANCELLED];

    protected $fillable = ['user_id', 'guest_token', 'input_url', 'options', 'status', 'expires_at'];

    protected $hidden = ['guest_token'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'preflight' => 'array',
            'warnings' => 'array',
            'cancel_requested_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function topLevelProducts(): HasMany
    {
        return $this->hasMany(Product::class)->whereNull('parent_id')->orderBy('position');
    }

    public function exports(): HasMany
    {
        return $this->hasMany(Export::class);
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, self::FINISHED, true);
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    public function hasResults(): bool
    {
        return in_array($this->status, [self::SUCCEEDED, self::PARTIAL, self::CANCELLED], true) && $this->products_found > 0;
    }

    public function maxProducts(): int
    {
        return (int) ($this->options['max_products'] ?? 25);
    }

    public function progressPercent(): ?int
    {
        $target = min($this->maxProducts(), $this->products_estimated ?? $this->maxProducts());

        return $target > 0 ? (int) min(100, round($this->products_found / $target * 100)) : null;
    }

    /** UI badge tone for the status (design-system vocabulary). */
    public function tone(): string
    {
        return match ($this->status) {
            self::SUCCEEDED => 'success',
            self::PARTIAL => 'warning',
            self::FAILED, self::BLOCKED => 'danger',
            self::PREFLIGHT, self::RUNNING => 'info',
            default => 'neutral',
        };
    }

    public static function guestTokenHash(string $token): string
    {
        return hash('sha256', $token);
    }
}
