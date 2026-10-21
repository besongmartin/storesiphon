<?php

namespace App\Domain\Compliance;

use App\Domain\Fetching\Exceptions\BlockedUrlException;
use App\Domain\Fetching\Exceptions\FetchException;
use App\Domain\Fetching\Exceptions\RobotsDisallowedException;
use App\Domain\Fetching\SafeHttpClient;
use App\Domain\Fetching\UrlGuard;
use App\Models\AuditLog;
use App\Models\ExtractionJob;
use App\Models\OptOutRequest;
use App\Models\Store;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Store owners can exclude their domain. To stop anyone from opting out a competitor, the owner proves
 * control by publishing a token at https://<domain>/.well-known/storesiphon-optout.txt.
 * (Blocking StoreSiphonBot in robots.txt also works and needs no request at all.)
 */
final class OptOutService
{
    public const PATH = '/.well-known/storesiphon-optout.txt';

    public function __construct(private readonly UrlGuard $guard, private readonly SafeHttpClient $client) {}

    public function request(string $domain, string $email, ?string $ip = null): OptOutRequest
    {
        $host = $this->host($domain);

        return OptOutRequest::create([
            'host' => $host,
            'email' => $email,
            'token' => 'storesiphon-optout-'.Str::lower(Str::random(32)),
            'ip' => $ip,
        ]);
    }

    /** Check the published token; on success, exclude the store and stop running extractions. */
    public function verify(OptOutRequest $request): bool
    {
        if ($request->verified_at !== null) {
            return true;
        }

        $verified = false;
        foreach (['https://'.$request->host, 'https://www.'.$request->host] as $origin) {
            try {
                $response = $this->client->get($origin.self::PATH, ['Accept' => 'text/plain']);
                if ($response->successful() && str_contains($response->body, $request->token)) {
                    $verified = true;
                    break;
                }
            } catch (RobotsDisallowedException) {
                // Only a robots.txt that really exists and blocks StoreSiphonBot counts as the owner's
                // opt-out; an unreachable robots.txt (treated as "disallow all" for safety) proves nothing.
                $robots = $this->client->robotsFor($this->guard->check($origin.'/'));
                if (! $robots->unreachable && ! $robots->isAllowed(self::PATH)) {
                    $verified = true;
                    break;
                }

                continue;
            } catch (FetchException) {
                continue;
            }
        }

        if (! $verified) {
            return false;
        }

        $request->forceFill(['verified_at' => now()])->save();
        $store = Store::firstOrCreate(['host' => $request->host], ['origin' => 'https://'.$request->host]);
        $before = ['opted_out_at' => $store->opted_out_at?->toIso8601String()];
        $store->forceFill(['opted_out_at' => now()])->save();

        // Stop anything currently running against this store.
        ExtractionJob::where('store_id', $store->id)->whereIn('status', ExtractionJob::ACTIVE)
            ->update(['cancel_requested_at' => now()]);

        AuditLog::record('store.opted_out', $store, $before, ['opted_out_at' => $store->opted_out_at->toIso8601String()],
            note: 'Verified opt-out request #'.$request->id);

        return true;
    }

    private function host(string $domain): string
    {
        $domain = trim($domain);
        $url = preg_match('#^[a-z][a-z0-9+.-]*://#i', $domain) ? $domain : 'https://'.$domain;
        try {
            return Store::normalizeHost($this->guard->check($url)->host);
        } catch (BlockedUrlException) {
            throw ValidationException::withMessages(['domain' => 'Enter your store\'s public domain, like shop.example.com.']);
        }
    }
}
