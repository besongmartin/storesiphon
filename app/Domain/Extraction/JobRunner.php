<?php

namespace App\Domain\Extraction;

use App\Domain\Extraction\Data\ProductData;
use App\Domain\Extraction\Preflight\Preflight;
use App\Domain\Extraction\Preflight\PreflightResult;
use App\Domain\Fetching\Exceptions\AccessDeniedException;
use App\Domain\Fetching\Exceptions\BlockedUrlException;
use App\Domain\Fetching\Exceptions\ChallengeDetectedException;
use App\Domain\Fetching\Exceptions\FetchException;
use App\Domain\Fetching\Exceptions\RetryLaterException;
use App\Domain\Fetching\Exceptions\RobotsDisallowedException;
use App\Models\ExtractionJob;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Executes one ExtractionJob: preflight → extract in batches → persist → final status.
 *
 * Products are saved as they arrive, so progress is visible immediately and a cancelled or failed
 * run keeps everything extracted so far.
 */
final class JobRunner
{
    public function __construct(
        private readonly Preflight $preflight,
        private readonly Extractor $extractor,
    ) {}

    public function run(ExtractionJob $job): void
    {
        if ($job->isFinished()) {
            return;
        }
        if ($job->cancel_requested_at !== null) {
            $this->finish($job, ExtractionJob::CANCELLED);

            return;
        }

        $job->forceFill(['status' => ExtractionJob::PREFLIGHT, 'started_at' => now()])->save();

        try {
            $result = $this->preflight->run($job->input_url);
            $store = $this->rememberStore($result);
            $job->forceFill([
                'store_id' => $store?->id,
                'preflight' => $result->toArray(),
                'products_estimated' => $result->estimatedProducts,
            ])->save();

            if ($store?->isOptedOut()) {
                $this->finish($job, ExtractionJob::BLOCKED, 'store_opted_out', 'The owner of this store has asked StoreSiphon not to extract it.');

                return;
            }
            if (! $result->canExtract()) {
                $this->finishFromPreflight($job, $result);

                return;
            }

            $job->forceFill(['status' => ExtractionJob::RUNNING])->save();

            $options = new ExtractionOptions(
                maxProducts: $job->maxProducts(),
                includeVariations: (bool) ($job->options['include_variations'] ?? true),
                shouldStop: fn () => ExtractionJob::whereKey($job->id)->whereNotNull('cancel_requested_at')->exists(),
            );

            $position = 0;
            $summary = $this->extractor->run($result, $options, function (array $batch) use ($job, &$position) {
                $this->persist($job, $batch, $position);
            });

            $job->forceFill([
                'methods_used' => implode(',', $summary->methods()),
                'warnings' => $summary->warnings ?: null,
            ])->save();

            $this->finish($job, match ($summary->status()) {
                'failed' => ExtractionJob::FAILED,
                'partial' => ExtractionJob::PARTIAL,
                default => ExtractionJob::SUCCEEDED,
            }, $summary->failed ? 'no_products' : null, $summary->failed ? 'No products could be extracted from this store.' : null);
        } catch (ExtractionCancelled) {
            $this->finish($job, ExtractionJob::CANCELLED);
        } catch (RetryLaterException $e) {
            $this->finish($job, ExtractionJob::FAILED, $e->reason(), 'The store asked us to slow down. Please try again later.');
        } catch (AccessDeniedException|ChallengeDetectedException|RobotsDisallowedException|BlockedUrlException $e) {
            $this->finish($job, $job->products_found > 0 ? ExtractionJob::PARTIAL : ExtractionJob::BLOCKED, $e->reason(), $this->explain($e));
        } catch (FetchException $e) {
            $this->finish($job, $job->products_found > 0 ? ExtractionJob::PARTIAL : ExtractionJob::FAILED, $e->reason(), 'The store could not be read reliably: '.$e->getMessage());
        } catch (Throwable $e) {
            $this->finish($job, ExtractionJob::FAILED, 'internal_error', 'Something went wrong on our side. The error has been logged.');
            report($e);
        }
    }

    /** @param list<ProductData> $batch */
    private function persist(ExtractionJob $job, array $batch, int &$position): void
    {
        DB::transaction(function () use ($job, $batch, &$position) {
            $variations = 0;
            foreach ($batch as $product) {
                $parentId = DB::table('products')->insertGetId(Product::rowFrom($product, $job->id, $position++));
                $rows = [];
                foreach ($product->variations as $i => $variation) {
                    $rows[] = Product::rowFrom($variation, $job->id, $i, $parentId);
                }
                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table('products')->insert($chunk);
                }
                $variations += count($rows);
            }

            ExtractionJob::whereKey($job->id)->update([
                'products_found' => DB::raw('products_found + '.count($batch)),
                'variations_found' => DB::raw('variations_found + '.$variations),
                'updated_at' => now(),
            ]);
        });
        $job->refresh();
    }

    private function rememberStore(PreflightResult $result): ?Store
    {
        $host = parse_url($result->origin, PHP_URL_HOST);
        if (! is_string($host) || $host === '' || $result->reasonCode === 'invalid_url') {
            return null;
        }

        return Store::updateOrCreate(['host' => Store::normalizeHost($host)], [
            'origin' => $result->origin,
            'name' => $result->storeName,
            'signals' => $result->signals,
            'last_preflight_at' => now(),
        ]);
    }

    private function finishFromPreflight(ExtractionJob $job, PreflightResult $result): void
    {
        $status = $result->status === PreflightResult::BLOCKED ? ExtractionJob::BLOCKED : ExtractionJob::FAILED;
        $message = match ($result->status) {
            PreflightResult::BLOCKED => $this->explainCode($result->reasonCode, $result->message),
            PreflightResult::RETRY_LATER => 'The store asked us to slow down. Please try again later.',
            PreflightResult::UNREACHABLE => 'We could not reach this store. Check the address and try again.',
            default => $result->message,
        };
        $this->finish($job, $status, $result->reasonCode, $message);
    }

    private function finish(ExtractionJob $job, string $status, ?string $code = null, ?string $message = null): void
    {
        $job->forceFill([
            'status' => $status,
            'error_code' => $code,
            'error_message' => $message,
            'finished_at' => now(),
        ])->save();
    }

    private function explain(FetchException $e): string
    {
        return $this->explainCode($e->reason(), $e->getMessage());
    }

    private function explainCode(?string $code, ?string $fallback): string
    {
        return match ($code) {
            'access_denied' => 'The store refused access (401/403). StoreSiphon does not bypass access controls.',
            'challenge_detected' => 'The store is protected by a bot challenge. StoreSiphon does not solve or bypass challenges.',
            'robots_disallowed' => 'The store\'s robots.txt does not allow these pages to be read.',
            'invalid_url', 'blocked_url' => 'This address cannot be used: '.($fallback ?? 'it is not a public website.'),
            default => $fallback ?? 'The store could not be read.',
        };
    }
}
