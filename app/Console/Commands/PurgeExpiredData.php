<?php

namespace App\Console\Commands;

use App\Models\Export;
use App\Models\ExtractionJob;
use App\Models\OptOutRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Data retention (architecture §6): extraction results and export files are deleted when they expire
 * (plan retention days; guest previews after one day). Unverified opt-out requests are removed after
 * 30 days because they contain an email address. Scheduled daily.
 */
class PurgeExpiredData extends Command
{
    protected $signature = 'storesiphon:purge {--dry-run : Report what would be deleted}';

    protected $description = 'Delete expired extractions, products and export files, and stale personal data';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $disk = Storage::disk(config('storesiphon.exports.disk'));

        // 1. Expired export files (jobs may live longer than an individual export link).
        $exports = 0;
        Export::where('expires_at', '<', now())->whereNotNull('path')->chunkById(200, function ($batch) use ($dry, $disk, &$exports) {
            foreach ($batch as $export) {
                $exports++;
                if (! $dry) {
                    $disk->delete($export->path);
                    $export->delete();
                }
            }
        });

        // 2. Expired extraction jobs: files, then rows (products and exports cascade).
        $jobs = 0;
        $products = 0;
        ExtractionJob::where('expires_at', '<', now())->whereNotIn('status', ExtractionJob::ACTIVE)
            ->chunkById(100, function ($batch) use ($dry, $disk, &$jobs, &$products) {
                foreach ($batch as $job) {
                    $jobs++;
                    $products += $job->products()->count();
                    if (! $dry) {
                        $disk->deleteDirectory('exports/'.$job->id);
                        $job->delete();
                    }
                }
            });

        // 3. Personal data that is no longer needed.
        $optOuts = OptOutRequest::whereNull('verified_at')->where('created_at', '<', now()->subDays(30));
        $optOutCount = $optOuts->count();
        if (! $dry) {
            $optOuts->delete();
        }

        $this->info(($dry ? '[dry run] ' : '')."Purged {$jobs} extraction(s) with {$products} product row(s), {$exports} export file(s), {$optOutCount} stale opt-out request(s).");

        return self::SUCCESS;
    }
}
