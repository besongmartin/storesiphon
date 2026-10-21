<?php

namespace App\Domain\Extraction;

use App\Domain\Extraction\Data\ProductData;
use App\Domain\Extraction\Preflight\PreflightResult;
use App\Domain\Extraction\Sources\ProductSource;
use App\Domain\Extraction\Sources\SitemapSource;
use App\Domain\Extraction\Sources\StoreApiSource;
use App\Domain\Fetching\Exceptions\AccessDeniedException;
use App\Domain\Fetching\Exceptions\FetchException;
use App\Domain\Fetching\Exceptions\RetryLaterException;
use App\Domain\Fetching\Exceptions\RobotsDisallowedException;
use App\Domain\Fetching\SafeHttpClient;
use InvalidArgumentException;

/**
 * Runs the extraction ladder for a preflighted store.
 *
 * Methods are tried in the preflight's order. If an API method stops working mid-run (network
 * failure, restriction), the extractor continues with the next method and skips products it already
 * has. Policy stops on public pages (bot challenge, 401/403) and RetryLater propagate to the caller.
 */
final class Extractor
{
    public function __construct(private readonly SafeHttpClient $client) {}

    /**
     * @param  callable(list<ProductData>): void  $onBatch  receives each batch of new products
     */
    public function run(PreflightResult $preflight, ExtractionOptions $options, callable $onBatch): ExtractionSummary
    {
        if (! $preflight->canExtract()) {
            throw new InvalidArgumentException('Preflight did not allow extraction: '.($preflight->reasonCode ?? $preflight->status));
        }

        $summary = new ExtractionSummary;
        $seen = [];

        foreach ($preflight->methods as $method) {
            if ($summary->products >= $options->maxProducts) {
                break;
            }
            $source = $this->source($method, $preflight);
            if ($source instanceof SitemapSource) {
                $source->skip(array_keys($seen)); // don't re-download pages the API already gave us
            }
            $remaining = new ExtractionOptions(
                maxProducts: $options->maxProducts - $summary->products,
                includeVariations: $options->includeVariations,
                categoryIds: $options->categoryIds,
                perPage: $options->perPage,
            );

            try {
                foreach ($source->pages($remaining) as $batch) {
                    $fresh = [];
                    foreach ($batch as $product) {
                        $key = self::key($product);
                        if (isset($seen[$key]) || $summary->products >= $options->maxProducts) {
                            continue;
                        }
                        $seen[$key] = true;
                        $fresh[] = $product;
                        $summary->products++;
                        $summary->variations += count($product->variations);
                    }
                    if ($fresh) {
                        $summary->methodsUsed[$method] = true;
                        $summary->lastMethod = $method;
                        $onBatch($fresh);
                    }
                    if ($summary->products >= $options->maxProducts) {
                        break;
                    }
                }
                if ($source instanceof SitemapSource && $source->skipped) {
                    $summary->warnings[] = count($source->skipped).' product page(s) could not be read.';
                    $summary->partial = true;
                }

                return $summary->finish();
            } catch (RetryLaterException $e) {
                throw $e;
            } catch (FetchException $e) {
                $isApi = $method !== SitemapSource::METHOD;
                $policyStopOnPages = ! $isApi && ! $e->isRetryable() && ! $e instanceof RobotsDisallowedException;
                if ($policyStopOnPages || ($e instanceof AccessDeniedException && ! $isApi)) {
                    throw $e;
                }
                $summary->warnings[] = "{$method} stopped ({$e->reason()}); continuing with the next method.";
                if ($summary->products > 0) {
                    $summary->partial = true;
                }
            }
        }

        if ($summary->products === 0) {
            $summary->failed = true;
        }

        return $summary->finish();
    }

    private function source(string $method, PreflightResult $preflight): ProductSource
    {
        return match ($method) {
            StoreApiSource::PRETTY, StoreApiSource::QUERY => new StoreApiSource($this->client, $preflight->origin, $method),
            SitemapSource::METHOD => new SitemapSource($this->client, $preflight->origin, $preflight->sitemaps),
            default => throw new InvalidArgumentException("Unknown extraction method {$method}"),
        };
    }

    /** De-duplicate across methods by permalink path (API ids and page ids agree, but URLs are universal). */
    public static function key(ProductData $product): string
    {
        return self::pathKey($product->permalink) ?? 'id:'.$product->externalId;
    }

    public static function pathKey(string $url): ?string
    {
        $path = rtrim((string) parse_url($url, PHP_URL_PATH), '/');

        return $path !== '' ? strtolower($path) : null;
    }
}
