<?php

namespace App\Domain\Billing;

use App\Jobs\ProcessPaymentEvent;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\PaymentEvent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Bitcoin billing rules (architecture §5, payment flow):
 *  - access is granted only when the provider's API reports the invoice confirmed; never from a browser
 *    redirect and never from a webhook body alone (webhooks only trigger a re-fetch);
 *  - status moves forward only; access is granted exactly once per invoice;
 *  - anything unusual (under/over/late payment, provider "Marked") is flagged for admin review.
 */
final class PaymentService
{
    /** Ordering for forward-only transitions. */
    private const RANK = [
        Invoice::PENDING => 0, Invoice::DETECTED => 1, Invoice::CONFIRMING => 2,
        Invoice::CONFIRMED => 3, Invoice::OVERPAID => 3, Invoice::EXPIRED => 3, Invoice::UNDERPAID => 3, Invoice::FAILED => 3,
        Invoice::REFUNDED => 4,
    ];

    public function __construct(private readonly PaymentProvider $provider) {}

    public function provider(): PaymentProvider
    {
        return $this->provider;
    }

    /** Create a local invoice and the provider invoice for one period of $plan. */
    public function checkout(User $user, Plan $plan): Invoice
    {
        if (! $this->provider->isConfigured()) {
            throw new RuntimeException('Bitcoin payments are not configured yet.');
        }
        if ($plan->isFree() || ! $plan->is_active) {
            throw new RuntimeException('This plan cannot be purchased.');
        }

        $invoice = Invoice::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'provider' => $this->provider->name(),
            'fiat_amount' => $plan->price_usd,
            'fiat_currency' => 'USD',
            'status' => Invoice::PENDING,
        ]);

        try {
            $remote = $this->provider->createInvoice($invoice, route('billing.invoices.show', $invoice));
        } catch (\Throwable $e) {
            $invoice->forceFill(['status' => Invoice::FAILED, 'review_note' => 'Provider invoice could not be created.'])->save();
            throw new RuntimeException('The payment page could not be created. Please try again in a moment.', 0, $e);
        }

        $invoice->forceFill([
            'provider_invoice_id' => $remote->id,
            'checkout_url' => $remote->checkoutUrl,
            'expires_at' => $remote->expiresAt,
            'btc_amount' => $remote->btcAmount,
            'rate' => $remote->rate,
            'rate_at' => now(),                       // rate snapshot taken when the invoice was created
            'provider_status' => $remote->status,
            'provider_additional_status' => $remote->additionalStatus,
            'last_checked_at' => now(),
        ])->save();

        AuditLog::record('invoice.created', $invoice, null, $invoice->only(['status', 'fiat_amount', 'btc_amount', 'rate', 'provider_invoice_id']), $user);

        return $invoice;
    }

    /** Re-read the invoice from the provider and apply it. Safe to call any number of times. */
    public function refresh(Invoice $invoice, string $source = 'refresh'): Invoice
    {
        if (! $invoice->provider_invoice_id) {
            return $invoice;
        }
        $remote = $this->provider->getInvoice($invoice->provider_invoice_id);

        return $this->apply($invoice, $remote, $source);
    }

    public function apply(Invoice $invoice, ProviderInvoice $remote, string $source): Invoice
    {
        // The provider invoice must belong to this order.
        if ($remote->orderId !== null && $remote->orderId !== $invoice->id) {
            $this->flag($invoice, 'Provider invoice order id does not match this invoice.');

            return $invoice;
        }

        return DB::transaction(function () use ($invoice, $remote, $source) {
            /** @var Invoice $locked */
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $before = $locked->only(['status', 'btc_paid', 'needs_review', 'provider_status', 'provider_additional_status']);

            [$status, $review] = $this->map($remote);
            $next = $this->canMove($locked->status, $status) ? $status : $locked->status;

            $locked->forceFill([
                'status' => $next,
                'provider_status' => $remote->status,
                'provider_additional_status' => $remote->additionalStatus,
                'btc_paid' => $remote->btcPaid ?? $locked->btc_paid,
                'btc_amount' => $locked->btc_amount ?? $remote->btcAmount,
                'expires_at' => $remote->expiresAt ?? $locked->expires_at,
                'last_checked_at' => now(),
            ]);
            if ($review !== null && ! $locked->needs_review) {
                $locked->needs_review = true;
                $locked->review_note = $review;
            }
            if (in_array($next, [Invoice::CONFIRMED, Invoice::OVERPAID], true) && $locked->settled_at === null) {
                $locked->settled_at = now();
            }
            $locked->save();

            if ($before['status'] !== $locked->status || $before['needs_review'] !== $locked->needs_review) {
                AuditLog::record('invoice.status_changed', $locked, $before,
                    $locked->only(['status', 'btc_paid', 'needs_review', 'provider_status', 'provider_additional_status']),
                    note: "via {$source}".($review ? "; {$review}" : ''));
            }

            if ($locked->isPaid() && $locked->granted_at === null) {
                $this->grant($locked);
            }

            return $locked;
        });
    }

    /**
     * Verify, record and queue a webhook. Returns 'accepted', 'duplicate' or 'ignored'.
     *
     * @param  array<string, string>  $headers  lower-cased
     */
    public function receiveWebhook(string $rawBody, array $headers): string
    {
        if (! $this->provider->verifyWebhook($rawBody, $headers)) {
            throw new InvalidWebhookSignature('Webhook signature is missing or invalid.');
        }
        $data = $this->provider->parseWebhook($rawBody);

        if ($this->provider instanceof BtcPayProvider && $data['store_id'] !== $this->provider->expectedStoreId()) {
            return 'ignored';
        }

        try {
            $event = PaymentEvent::create([
                'provider' => $this->provider->name(),
                'delivery_id' => $data['delivery_id'],
                'type' => $data['type'],
                'provider_invoice_id' => $data['invoice_id'],
                'payload' => json_decode($rawBody, true),
            ]);
        } catch (QueryException $e) {
            if ($this->isDuplicate($e)) {
                return 'duplicate'; // same delivery seen before: nothing to do
            }
            throw $e;
        }

        ProcessPaymentEvent::dispatch($event->id);

        return 'accepted';
    }

    public function processEvent(PaymentEvent $event): void
    {
        if ($event->processed_at !== null) {
            return;
        }
        $invoice = $event->provider_invoice_id
            ? Invoice::where('provider', $event->provider)->where('provider_invoice_id', $event->provider_invoice_id)->first()
            : null;

        if ($invoice) {
            $this->refresh($invoice, 'webhook '.$event->type); // re-fetch: the webhook body is never trusted
        }
        $event->forceFill(['processed_at' => now(), 'error' => $invoice ? null : 'Unknown invoice'])->save();
    }

    /** Admin decision on a flagged invoice. Grants the period when $grant is true (once only). */
    public function resolve(Invoice $invoice, User $admin, string $note, bool $grant): Invoice
    {
        return DB::transaction(function () use ($invoice, $admin, $note, $grant) {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $before = $locked->only(['status', 'needs_review', 'review_note', 'granted_at']);

            $locked->forceFill(['needs_review' => false, 'review_note' => trim($note)])->save();
            if ($grant && $locked->granted_at === null) {
                $this->grant($locked, 'admin');
            }

            AuditLog::record($grant ? 'invoice.resolved_granted' : 'invoice.resolved', $locked, $before,
                $locked->fresh()->only(['status', 'needs_review', 'review_note', 'granted_at']), $admin, $note);

            return $locked->fresh();
        });
    }

    /**
     * Provider status → [our status, review reason or null].
     *
     * @return array{0: string, 1: ?string}
     */
    public function map(ProviderInvoice $remote): array
    {
        $extra = $remote->additionalStatus;
        $paidSomething = $remote->btcPaid !== null && bccomp($remote->btcPaid, '0', 8) > 0;

        return match ($remote->status) {
            'Settled', 'Complete', 'Confirmed' => match ($extra) {
                'PaidOver' => [Invoice::OVERPAID, 'Customer paid more than the invoice amount.'],
                'Marked' => [Invoice::CONFIRMED, 'Marked as settled manually in BTCPay.'],
                default => [Invoice::CONFIRMED, null],
            },
            'Processing', 'Paid' => [Invoice::CONFIRMING, $extra === 'PaidOver' ? 'Customer paid more than the invoice amount.' : null],
            'Expired' => match ($extra) {
                'PaidPartial' => [Invoice::UNDERPAID, 'Invoice expired after a partial payment.'],
                'PaidLate' => [Invoice::EXPIRED, 'Payment arrived after the invoice expired.'],
                default => [$paidSomething ? Invoice::UNDERPAID : Invoice::EXPIRED, $paidSomething ? 'Invoice expired after a partial payment.' : null],
            },
            'Invalid' => [Invoice::FAILED, $paidSomething ? 'Invoice marked invalid after a payment was seen.' : null],
            default => [$paidSomething ? Invoice::DETECTED : Invoice::PENDING, null], // New
        };
    }

    private function canMove(string $from, string $to): bool
    {
        return (self::RANK[$to] ?? 0) > (self::RANK[$from] ?? 0);
    }

    /** Create the subscription period for a paid invoice. Caller holds the invoice row lock. */
    private function grant(Invoice $invoice, string $source = 'invoice'): void
    {
        $plan = $invoice->plan()->firstOrFail();
        $user = $invoice->user()->firstOrFail();

        // Buying the same plan again extends it; another plan starts now.
        $latestSame = Subscription::where('user_id', $user->id)->where('plan_id', $plan->id)
            ->where('ends_at', '>', now())->orderByDesc('ends_at')->first();
        $start = $latestSame?->ends_at ?? now();
        $start = Carbon::parse($start);

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'invoice_id' => $invoice->id,       // unique: a second grant for this invoice fails at the database
            'starts_at' => $start,
            'ends_at' => $start->copy()->addDays($plan->period_days),
            'source' => $source,
        ]);
        $invoice->forceFill(['granted_at' => now()])->save();

        AuditLog::record('subscription.granted', $subscription, null, $subscription->only(['plan_id', 'starts_at', 'ends_at', 'invoice_id']),
            note: "plan {$plan->slug} via {$source}");
    }

    private function flag(Invoice $invoice, string $reason): void
    {
        if (! $invoice->needs_review) {
            $invoice->forceFill(['needs_review' => true, 'review_note' => $reason])->save();
            AuditLog::record('invoice.flagged', $invoice, null, ['needs_review' => true], note: $reason);
        }
    }

    private function isDuplicate(QueryException $e): bool
    {
        return in_array($e->errorInfo[1] ?? null, [1062, 19], true) || str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'UNIQUE');
    }
}
