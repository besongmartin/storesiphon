<?php

namespace App\Providers;

use App\Domain\Billing\BtcPayProvider;
use App\Domain\Billing\PaymentProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Payment provider adapter (D6: BTCPay Server via Greenfield). Swap here to change providers.
        $this->app->singleton(PaymentProvider::class, fn ($app) => new BtcPayProvider(
            $app->make(HttpFactory::class),
            (array) config('services.btcpay'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        // Worker heartbeat (read by storesiphon:health), written at most every 30 s.
        Event::listen(Looping::class, function () {
            if (! Cache::has('heartbeat:worker:fresh')) {
                Cache::put('heartbeat:worker', now()->toIso8601String(), 3600);
                Cache::put('heartbeat:worker:fresh', 1, 30);
            }
        });

        // /up (load balancer / uptime monitor) fails when the database or cache is unreachable.
        Event::listen(DiagnosingHealth::class, function () {
            DB::select('select 1');
            Cache::put('health:up', 1, 5);
        });

        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(6)->by($request->user()?->id ?: $request->ip()));
    }
}
