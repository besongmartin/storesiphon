<?php

namespace App\Providers;

use App\Domain\Billing\BtcPayProvider;
use App\Domain\Billing\PaymentProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
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
        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(6)->by($request->user()?->id ?: $request->ip()));
    }
}
