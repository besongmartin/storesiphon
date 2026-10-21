<?php

namespace App\Domain\Security;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Cloudflare Turnstile (privacy-friendly CAPTCHA alternative) for public forms.
 * Disabled until TURNSTILE_SITE_KEY and TURNSTILE_SECRET_KEY are set.
 */
final class Turnstile
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(private readonly HttpFactory $http) {}

    public function enabled(): bool
    {
        return filled(config('services.turnstile.site_key')) && filled(config('services.turnstile.secret_key'));
    }

    public function siteKey(): ?string
    {
        return config('services.turnstile.site_key');
    }

    /** @throws ValidationException */
    public function verifyOrFail(?string $token, ?string $ip, string $field = 'turnstileToken'): void
    {
        if (! $this->enabled()) {
            return;
        }
        $ok = false;
        if (filled($token)) {
            try {
                $response = $this->http->asForm()->timeout(10)->post(self::VERIFY_URL, array_filter([
                    'secret' => config('services.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
                $ok = (bool) $response->json('success');
            } catch (Throwable $e) {
                report($e);
            }
        }
        if (! $ok) {
            throw ValidationException::withMessages([$field => 'Please complete the security check and try again.']);
        }
    }
}
