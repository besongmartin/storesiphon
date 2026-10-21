<?php

namespace App\Jobs;

use App\Domain\Billing\PaymentService;
use App\Models\PaymentEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPaymentEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 30, 60, 300];

    public function __construct(public readonly int $eventId)
    {
        $this->onQueue('payments');
    }

    public function handle(PaymentService $payments): void
    {
        if ($event = PaymentEvent::find($this->eventId)) {
            $payments->processEvent($event);
        }
    }
}
