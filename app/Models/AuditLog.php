<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** Record an action. $actor null means the system (webhook, scheduler). */
    public static function record(string $action, Model $subject, ?array $before, ?array $after, ?User $actor = null, ?string $note = null): self
    {
        return static::create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => class_basename($subject),
            'subject_id' => (string) $subject->getKey(),
            'before' => $before,
            'after' => $after,
            'note' => $note,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
