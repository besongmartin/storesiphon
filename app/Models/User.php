<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    public const ROLE_USER = 'user';

    public const ROLE_ADMIN = 'admin';

    /**
     * The attributes that are mass assignable.
     *
     * Role and suspension are deliberately not mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** The subscription covering right now (latest-ending wins when periods overlap). */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->with('plan')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->orderByDesc('ends_at')
            ->first();
    }

    public function currentPlan(): Plan
    {
        return $this->activeSubscription()?->plan ?? Plan::free();
    }

    /** Start of the current usage period: the paid period's start, or the calendar month for Free. */
    public function periodStart(): Carbon
    {
        return $this->activeSubscription()?->starts_at ?? now()->startOfMonth();
    }

    /** Products extracted (and reserved by running jobs) in the current period. */
    public function productsUsedThisPeriod(): int
    {
        $jobs = ExtractionJob::ownedBy($this)->where('created_at', '>=', $this->periodStart());
        $done = (clone $jobs)->whereNotIn('status', ExtractionJob::ACTIVE)->sum('products_found');
        $reserved = (clone $jobs)->whereIn('status', ExtractionJob::ACTIVE)->get()->sum(fn (ExtractionJob $j) => $j->maxProducts());

        return (int) $done + (int) $reserved;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * Initials for the avatar chip in the app shell.
     */
    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];

        return mb_strtoupper(collect($parts)->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode(''));
    }
}
