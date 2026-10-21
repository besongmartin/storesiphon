<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser security headers (architecture §6).
 *
 * CSP: scripts only from our origin with a per-request nonce (Vite and Livewire add it automatically),
 * plus Cloudflare Turnstile. 'unsafe-eval' is required by the Alpine build bundled with Livewire 3,
 * which evaluates x-* expressions at runtime. Product images are loaded from the stores themselves,
 * so img-src allows https:. Nothing may frame StoreSiphon.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $hot = app()->environment('local') && Vite::isRunningHot() ? $this->hotOrigin() : null;

        $csp = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval' https://challenges.cloudflare.com".($hot ? " {$hot}" : ''),
            "style-src 'self' 'unsafe-inline'".($hot ? " {$hot}" : ''),
            "img-src 'self' data: https:",
            "font-src 'self'".($hot ? " {$hot}" : ''),
            "connect-src 'self'".($hot ? ' '.$hot.' '.str_replace('http', 'ws', $hot) : ''),
            'frame-src https://challenges.cloudflare.com',
            "form-action 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "object-src 'none'",
        ];
        if (app()->environment('production')) {
            $csp[] = 'upgrade-insecure-requests';
        }

        $headers = [
            'Content-Security-Policy' => implode('; ', $csp),
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];
        if ($request->isSecure() && app()->environment('production')) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    private function hotOrigin(): ?string
    {
        $file = public_path('hot');
        $url = is_file($file) ? trim((string) file_get_contents($file)) : null;

        return $url ? rtrim($url, '/') : null;
    }
}
