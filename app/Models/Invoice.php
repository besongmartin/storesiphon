<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Bitcoin payment request for one plan period.
 *
 * Status moves forward only: pending → detected → confirming → confirmed.
 * Terminal alternatives: expired, underpaid, overpaid (paid and granted, flagged), failed, refunded.
 */
class Invoice extends Model
{
    use HasUuids;

    public const PENDING = 'pending';

    public const DETECTED = 'detected';

    public const CONFIRMING = 'confirming';

    public const CONFIRMED = 'confirmed';

    public const EXPIRED = 'expired';

    public const UNDERPAID = 'underpaid';

    public const OVERPAID = 'overpaid';

    public const FAILED = 'failed';

    public const REFUNDED = 'refunded';

    public const OPEN = [self::PENDING, self::DETECTED, self::CONFIRMING];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fiat_amount' => 'decimal:2',
            'btc_amount' => 'decimal:8',
            'btc_paid' => 'decimal:8',
            'rate' => 'decimal:2',
            'rate_at' => 'datetime',
            'expires_at' => 'datetime',
            'settled_at' => 'datetime',
            'granted_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'needs_review' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function isPaid(): bool
    {
        return in_array($this->status, [self::CONFIRMED, self::OVERPAID], true);
    }

    public function tone(): string
    {
        return match ($this->status) {
            self::CONFIRMED => 'success',
            self::OVERPAID, self::UNDERPAID => 'warning',
            self::FAILED => 'danger',
            self::DETECTED, self::CONFIRMING => 'info',
            default => 'neutral',
        };
    }

    public function label(): string
    {
        return match ($this->status) {
            self::PENDING => 'Awaiting payment',
            self::DETECTED => 'Payment detected',
            self::CONFIRMING => 'Confirming on-chain',
            self::CONFIRMED => 'Paid',
            self::EXPIRED => 'Expired',
            self::UNDERPAID => 'Underpaid',
            self::OVERPAID => 'Paid (overpaid)',
            self::FAILED => 'Failed',
            self::REFUNDED => 'Refunded',
            default => ucfirst($this->status),
        };
    }
}
