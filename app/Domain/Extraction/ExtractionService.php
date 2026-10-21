<?php

namespace App\Domain\Extraction;

use App\Domain\Fetching\Exceptions\BlockedUrlException;
use App\Domain\Fetching\UrlGuard;
use App\Jobs\GenerateExport;
use App\Jobs\RunExtraction;
use App\Models\Export;
use App\Models\ExtractionJob;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Starts extractions and exports with all user-facing checks in one place.
 */
final class ExtractionService
{
    public function __construct(private readonly UrlGuard $guard) {}

    /** @param array{max_products?: int, include_variations?: bool} $options */
    public function start(User $user, string $url, array $options = []): ExtractionJob
    {
        $plan = $user->currentPlan();
        $url = $this->validateUrl($url);

        $running = ExtractionJob::ownedBy($user)->whereIn('status', ExtractionJob::ACTIVE)->count();
        if ($running >= $plan->concurrent_jobs) {
            throw ValidationException::withMessages(['url' => $plan->concurrent_jobs === 1
                ? 'You already have an extraction running. Wait for it to finish or cancel it first.'
                : "Your plan runs up to {$plan->concurrent_jobs} extractions at once. Wait for one to finish first."]);
        }

        // Monthly (or per paid period) product quota; running jobs reserve their maximum.
        $remaining = $plan->product_quota - $user->productsUsedThisPeriod();
        if ($remaining <= 0) {
            throw ValidationException::withMessages(['url' => "You have used all {$plan->product_quota} products in your {$plan->name} plan for this period."
                .($plan->isFree() ? ' Upgrade to keep extracting.' : '')]);
        }

        $requested = (int) ($options['max_products'] ?? $plan->max_products_per_job);
        $job = ExtractionJob::create([
            'user_id' => $user->id,
            'input_url' => $url,
            'status' => ExtractionJob::QUEUED,
            'options' => [
                'max_products' => max(1, min($requested, $plan->max_products_per_job, $remaining)),
                'include_variations' => (bool) ($options['include_variations'] ?? true),
            ],
            'expires_at' => now()->addDays($plan->retention_days),
        ]);

        RunExtraction::dispatch($job->id);

        return $job;
    }

    /** Anonymous "try it" preview: small, rate limited per IP, no exports. */
    public function startGuest(string $guestToken, string $ip, string $url): ExtractionJob
    {
        $limits = config('storesiphon.limits.guest');
        $url = $this->validateUrl($url);

        $key = 'guest-extraction:'.$ip;
        if (RateLimiter::tooManyAttempts($key, $limits['jobs_per_hour_per_ip'])) {
            throw ValidationException::withMessages(['url' => 'You have used the free preview a few times already. Create a free account to keep going.']);
        }
        RateLimiter::hit($key, 3600);

        $job = ExtractionJob::create([
            'guest_token' => ExtractionJob::guestTokenHash($guestToken),
            'input_url' => $url,
            'status' => ExtractionJob::QUEUED,
            'options' => ['max_products' => $limits['max_products_per_job'], 'include_variations' => true],
            'expires_at' => now()->addDays($limits['retention_days']),
        ]);

        RunExtraction::dispatch($job->id);

        return $job;
    }

    public function cancel(ExtractionJob $job): void
    {
        if ($job->isActive()) {
            $job->forceFill(['cancel_requested_at' => now()])->save();
        }
    }

    public function export(ExtractionJob $job, string $format, bool $htmlDescriptions = false): Export
    {
        $plan = $job->user?->currentPlan();
        if (! in_array($format, config('storesiphon.exports.formats'), true) || ($plan && ! in_array($format, $plan->export_formats, true))) {
            throw ValidationException::withMessages(['format' => 'Unsupported export format.']);
        }

        $export = Export::create([
            'extraction_job_id' => $job->id,
            'format' => $format,
            'options' => ['html_descriptions' => $htmlDescriptions],
            'status' => Export::QUEUED,
        ]);
        GenerateExport::dispatch($export->id);

        return $export;
    }

    private function validateUrl(string $url): string
    {
        $url = trim($url);
        $withScheme = preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) ? $url : 'https://'.$url;
        try {
            $safe = $this->guard->check($withScheme);
        } catch (BlockedUrlException $e) {
            throw ValidationException::withMessages(['url' => 'Enter the public address of a WooCommerce store, like shop.example.com. ('.$e->getMessage().')']);
        }

        $store = Store::where('host', Store::normalizeHost($safe->host))->first();
        if ($store?->isOptedOut()) {
            throw ValidationException::withMessages(['url' => 'The owner of this store has asked StoreSiphon not to extract it.']);
        }

        return $safe->origin().'/';
    }
}
