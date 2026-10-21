<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;

/**
 * Monolog tap: replaces configured secret values (payment API keys, webhook secrets, app key) with
 * [redacted] in every log message and context, whatever code logged them.
 */
class RedactSecrets
{
    public function __invoke(Logger $logger): void
    {
        $secrets = array_values(array_filter([
            config('services.btcpay.api_key'),
            config('services.btcpay.webhook_secret'),
            config('app.key'),
            config('database.connections.'.config('database.default').'.password'),
        ], fn ($s) => is_string($s) && strlen($s) >= 8));

        if (! $secrets) {
            return;
        }

        foreach ($logger->getHandlers() as $handler) {
            $handler->pushProcessor(function (LogRecord $record) use ($secrets): LogRecord {
                $scrub = function ($value) use (&$scrub, $secrets) {
                    if (is_string($value)) {
                        return str_replace($secrets, '[redacted]', $value);
                    }
                    if (is_array($value)) {
                        return array_map($scrub, $value);
                    }

                    return $value;
                };

                return $record->with(message: $scrub($record->message), context: $scrub($record->context), extra: $scrub($record->extra));
            });
        }
    }
}
