<?php

namespace App\Console\Commands;

use App\Domain\Billing\PaymentProvider;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Operational health: database, cache, worker and scheduler heartbeats, queue backlog, failed jobs,
 * payment configuration and the payment review queue. Exit code 1 when something needs attention,
 * so it can drive an uptime monitor or cron alert.
 */
class HealthCheck extends Command
{
    protected $signature = 'storesiphon:health {--json : Machine-readable output}';

    protected $description = 'Check database, cache, queue worker, scheduler and payments health';

    public function handle(PaymentProvider $provider): int
    {
        $checks = [];
        $checks['database'] = $this->check(fn () => DB::select('select 1') ? 'ok' : 'fail');
        $checks['cache'] = $this->check(function () {
            Cache::put('health:probe', 1, 10);

            return Cache::get('health:probe') === 1 ? 'ok' : 'fail';
        });
        $checks['worker'] = $this->heartbeat('heartbeat:worker', 5);
        $checks['scheduler'] = $this->heartbeat('heartbeat:scheduler', 3);
        $checks['queue_backlog'] = $this->check(function () {
            if (config('queue.default') !== 'database') {
                return 'ok (not database queue)';
            }
            $oldest = DB::table('jobs')->min('available_at');

            return $oldest && now()->getTimestamp() - (int) $oldest > 600 ? 'warn: jobs waiting over 10 minutes' : 'ok';
        });
        $checks['failed_jobs'] = $this->check(fn () => ($n = DB::table('failed_jobs')->where('failed_at', '>', now()->subDay())->count()) > 0 ? "warn: {$n} in 24h" : 'ok');
        $checks['payments'] = $provider->isConfigured() ? 'ok' : 'warn: BTCPay not configured';
        $checks['payment_review'] = ($n = Invoice::where('needs_review', true)->count()) > 0 ? "warn: {$n} invoice(s) need review" : 'ok';

        if ($this->option('json')) {
            $this->line(json_encode($checks, JSON_PRETTY_PRINT));
        } else {
            $this->table(['Check', 'Status'], collect($checks)->map(fn ($v, $k) => [$k, $v])->values()->all());
        }

        return collect($checks)->contains(fn ($v) => str_starts_with($v, 'fail')) ? self::FAILURE : self::SUCCESS;
    }

    private function heartbeat(string $key, int $maxMinutes): string
    {
        $at = Cache::get($key);
        if (! $at) {
            return 'fail: no heartbeat yet';
        }
        $age = Carbon::parse($at)->diffInMinutes(now());

        return $age > $maxMinutes ? "fail: last seen {$age} min ago" : 'ok';
    }

    private function check(callable $fn): string
    {
        try {
            return $fn();
        } catch (Throwable $e) {
            return 'fail: '.class_basename($e);
        }
    }
}
