<?php

namespace App\Jobs;

use App\Domain\Export\Writers;
use App\Models\Export;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class GenerateExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 900;

    public function __construct(public readonly string $exportId)
    {
        $this->onQueue(config('storesiphon.queue'));
    }

    public function handle(): void
    {
        $export = Export::with('job')->find($this->exportId);
        if (! $export || ! $export->job) {
            return;
        }
        $export->forceFill(['status' => Export::RUNNING, 'error' => null])->save();

        $disk = config('storesiphon.exports.disk');
        $format = Writers::for($export->format);
        $relative = "exports/{$export->job->id}/{$export->id}.{$format}";
        Storage::disk($disk)->makeDirectory(dirname($relative));
        $absolute = Storage::disk($disk)->path($relative);

        $rows = Writers::{$format}($export->job, $absolute, $export->options ?? []);

        $host = parse_url($export->job->preflight['origin'] ?? $export->job->input_url, PHP_URL_HOST) ?: 'store';
        $export->forceFill([
            'status' => Export::READY,
            'disk' => $disk,
            'path' => $relative,
            'filename' => 'storesiphon-'.Str::slug(str_replace('.', '-', $host)).'-'.now()->format('Ymd-His').'.'.$format,
            'size_bytes' => filesize($absolute) ?: 0,
            'row_count' => $rows,
            'expires_at' => $export->job->expires_at ?? now()->addDays(7),
        ])->save();
    }

    public function failed(?Throwable $e): void
    {
        Export::whereKey($this->exportId)->update([
            'status' => Export::FAILED,
            'error' => 'The export could not be generated. Please try again.',
        ]);
    }
}
