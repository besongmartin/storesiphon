<?php

namespace App\Jobs;

use App\Domain\Extraction\JobRunner;
use App\Models\ExtractionJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunExtraction implements ShouldQueue
{
    use Queueable;

    /** Extraction is not idempotent mid-run; never retry automatically. */
    public int $tries = 1;

    /** Large catalogues with crawl delays can take a long time. */
    public int $timeout = 3600;

    public function __construct(public readonly string $jobId)
    {
        $this->onQueue(config('storesiphon.queue'));
    }

    public function handle(JobRunner $runner): void
    {
        $job = ExtractionJob::find($this->jobId);
        if ($job) {
            $runner->run($job);
        }
    }

    public function failed(?Throwable $e): void
    {
        ExtractionJob::whereKey($this->jobId)
            ->whereIn('status', ExtractionJob::ACTIVE)
            ->update([
                'status' => ExtractionJob::FAILED,
                'error_code' => 'worker_failed',
                'error_message' => 'The extraction worker stopped unexpectedly. Please try again.',
                'finished_at' => now(),
            ]);
    }
}
