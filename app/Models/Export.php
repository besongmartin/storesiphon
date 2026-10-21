<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Export extends Model
{
    use HasUlids;

    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const READY = 'ready';

    public const FAILED = 'failed';

    protected $fillable = ['extraction_job_id', 'format', 'options', 'status', 'expires_at'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ExtractionJob::class, 'extraction_job_id');
    }

    public function isReady(): bool
    {
        return $this->status === self::READY && $this->path !== null;
    }
}
