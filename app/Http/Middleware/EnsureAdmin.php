<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Administration requires the admin role AND confirmed two-factor authentication.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user?->isAdmin(), 403);

        if ($user->two_factor_confirmed_at === null) {
            return redirect()->route('settings.security')
                ->with('status', 'Turn on two-factor authentication to use the admin area.');
        }

        return $next($request);
    }
}
