<?php

namespace App\Console\Commands;

use App\Domain\Fetching\Exceptions\FetchException;
use App\Domain\Fetching\SafeHttpClient;
use App\Domain\Fetching\UrlGuard;
use Illuminate\Console\Command;

/**
 * Diagnostic: fetch one URL through the safe fetcher and show what it did.
 * Use only on stores you are allowed to scrape; robots.txt and rate limits still apply.
 */
class FetchProbe extends Command
{
    protected $signature = 'fetch:probe {url : Full URL to fetch through SafeHttpClient}';

    protected $description = 'Fetch one URL through the safe fetcher and report status, pinned IP, robots.txt and timing';

    public function handle(SafeHttpClient $client, UrlGuard $guard): int
    {
        $url = (string) $this->argument('url');

        try {
            $safe = $guard->check($url);
            $robots = $client->robotsFor($safe);
            $this->line('robots.txt   crawl-delay: '.($robots->crawlDelay ?? 'none').' · sitemaps: '.count($robots->sitemaps).' · allowed: '.($robots->isAllowed($safe->pathAndQuery) ? 'yes' : 'no'));

            $start = microtime(true);
            $response = $client->get($url);
            $ms = (int) round((microtime(true) - $start) * 1000);
        } catch (FetchException $e) {
            $this->error("Stopped: {$e->reason()} — {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->table(['Field', 'Value'], [
            ['Status', $response->status],
            ['Final URL', $response->url],
            ['Connected IP', $response->ip ?? 'n/a'],
            ['Redirects', $response->redirects],
            ['Content-Type', $response->header('content-type') ?? 'n/a'],
            ['Bytes', number_format(strlen($response->body))],
            ['Time', "{$ms} ms"],
        ]);

        return self::SUCCESS;
    }
}
