<?php

use App\Domain\Billing\PaymentProvider;
use App\Domain\Billing\PaymentService;
use App\Livewire\Billing\Index as BillingIndex;
use App\Livewire\Billing\InvoiceShow;
use App\Logging\RedactSecrets;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\PaymentEvent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Support\FakeBtcPay;

beforeEach(function () {
    Carbon::setTestNow('2026-10-21 12:00:00');
    $this->btcpay = (new FakeBtcPay)->install();
    $this->user = User::factory()->create(['email' => 'buyer@example.org']);
    $this->starter = Plan::where('slug', 'starter')->sole();
});

function buy(User $user, Plan $plan): Invoice
{
    return app(PaymentService::class)->checkout($user, $plan);
}

function postWebhook($test, string $body, array $headers)
{
    return $test->call('POST', '/webhooks/btcpay', [], [], [], collect($headers)->mapWithKeys(fn ($v, $k) => ['HTTP_'.strtoupper(str_replace('-', '_', $k)) => $v])->all(), $body);
}

it('creates a BTCPay invoice with a rate snapshot and no personal data', function () {
    $invoice = buy($this->user, $this->starter);

    expect($invoice->status)->toBe(Invoice::PENDING)
        ->and($invoice->provider_invoice_id)->toBe('BTCPAY1')
        ->and($invoice->checkout_url)->toBe(FakeBtcPay::URL.'/i/BTCPAY1')
        ->and((string) $invoice->fiat_amount)->toBe('19.00')
        ->and((string) $invoice->btc_amount)->toBe('0.00031666')
        ->and((string) $invoice->rate)->toBe('60000.00')
        ->and($invoice->rate_at)->not->toBeNull();

    $sent = $this->btcpay->created[0];
    expect($sent['metadata'])->toBe(['orderId' => $invoice->id, 'itemDesc' => 'StoreSiphon Starter – 30 days'])
        ->and($sent['checkout']['speedPolicy'])->toBe('MediumSpeed')   // 1 confirmation (D8)
        ->and(json_encode($sent))->not->toContain('buyer@example.org');
    expect(AuditLog::where('action', 'invoice.created')->count())->toBe(1);
});

it('starts checkout from the billing page', function () {
    Livewire::actingAs($this->user)->test(BillingIndex::class)->call('buy', 'starter')->assertRedirect();

    expect(Invoice::sole()->user_id)->toBe($this->user->id);
});

it('does not grant access when the customer simply returns to the invoice page', function () {
    $invoice = buy($this->user, $this->starter);

    Livewire::actingAs($this->user)->test(InvoiceShow::class, ['invoice' => $invoice])->call('check')->assertSee('Awaiting payment');

    expect($invoice->refresh()->status)->toBe(Invoice::PENDING)
        ->and(Subscription::count())->toBe(0)
        ->and($this->user->currentPlan()->slug)->toBe('free');
});

it('rejects webhooks with a missing or wrong signature', function () {
    $invoice = buy($this->user, $this->starter);
    [$body, $headers] = FakeBtcPay::webhook($invoice->provider_invoice_id);

    postWebhook($this, $body, ['Content-Type' => 'application/json'])->assertStatus(401);
    postWebhook($this, $body, ['BTCPay-Sig' => 'sha256='.str_repeat('0', 64)])->assertStatus(401);
    postWebhook($this, $body.' ', $headers)->assertStatus(401);           // body altered after signing

    expect(PaymentEvent::count())->toBe(0)->and(Subscription::count())->toBe(0);
});

it('grants exactly one period on a verified Settled webhook, re-checking the provider', function () {
    $invoice = buy($this->user, $this->starter);
    $this->btcpay->set($invoice->provider_invoice_id, 'Settled', 'None', '0.00031666');
    [$body, $headers] = FakeBtcPay::webhook($invoice->provider_invoice_id, deliveryId: 'dlv_1');

    postWebhook($this, $body, $headers)->assertOk()->assertJson(['status' => 'accepted']);
    postWebhook($this, $body, $headers)->assertOk()->assertJson(['status' => 'duplicate']);   // redelivery

    $invoice->refresh();
    $sub = Subscription::sole();
    expect($invoice->status)->toBe(Invoice::CONFIRMED)
        ->and($invoice->granted_at)->not->toBeNull()
        ->and($sub->invoice_id)->toBe($invoice->id)
        ->and($sub->starts_at->toDateTimeString())->toBe('2026-10-21 12:00:00')
        ->and($sub->ends_at->toDateTimeString())->toBe('2026-11-20 12:00:00')
        ->and($this->user->currentPlan()->slug)->toBe('starter')
        ->and(PaymentEvent::count())->toBe(1)
        ->and(PaymentEvent::sole()->processed_at)->not->toBeNull()
        ->and(AuditLog::where('action', 'subscription.granted')->count())->toBe(1);
});

it('never trusts the webhook body: the provider must confirm', function () {
    $invoice = buy($this->user, $this->starter); // provider still says "New"
    [$body, $headers] = FakeBtcPay::webhook($invoice->provider_invoice_id, 'InvoiceSettled', extra: ['status' => 'Settled', 'paid' => true]);

    postWebhook($this, $body, $headers)->assertOk();

    expect($invoice->refresh()->status)->toBe(Invoice::PENDING)->and(Subscription::count())->toBe(0);
});

it('ignores webhooks for another BTCPay store', function () {
    $invoice = buy($this->user, $this->starter);
    $this->btcpay->set($invoice->provider_invoice_id, 'Settled', 'None', '0.00031666');
    [$body, $headers] = FakeBtcPay::webhook($invoice->provider_invoice_id, store: 'store_OTHER');

    postWebhook($this, $body, $headers)->assertOk()->assertJson(['status' => 'ignored']);
    expect(Subscription::count())->toBe(0);
});

it('moves through detected and confirming before granting', function () {
    $invoice = buy($this->user, $this->starter);
    $payments = app(PaymentService::class);

    $this->btcpay->set($invoice->provider_invoice_id, 'New', 'PaidPartial', '0.0001');
    expect($payments->refresh($invoice)->status)->toBe(Invoice::DETECTED);

    $this->btcpay->set($invoice->provider_invoice_id, 'Processing', 'None', '0.00031666');
    expect($payments->refresh($invoice)->status)->toBe(Invoice::CONFIRMING)->and(Subscription::count())->toBe(0);

    $this->btcpay->set($invoice->provider_invoice_id, 'Settled', 'None', '0.00031666');
    expect($payments->refresh($invoice)->status)->toBe(Invoice::CONFIRMED)->and(Subscription::count())->toBe(1);

    // Status never moves backwards, and a later re-check never grants twice.
    $this->btcpay->set($invoice->provider_invoice_id, 'Processing');
    expect($payments->refresh($invoice)->status)->toBe(Invoice::CONFIRMED)->and(Subscription::count())->toBe(1);
});

it('flags payment mismatches for review', function (string $status, string $additional, string $paid, string $expected, bool $granted) {
    $invoice = buy($this->user, $this->starter);
    $this->btcpay->set($invoice->provider_invoice_id, $status, $additional, $paid);

    $invoice = app(PaymentService::class)->refresh($invoice);

    expect($invoice->status)->toBe($expected)
        ->and($invoice->needs_review)->toBeTrue()
        ->and($invoice->review_note)->not->toBeEmpty()
        ->and(Subscription::count())->toBe($granted ? 1 : 0);
})->with([
    'underpaid then expired' => ['Expired', 'PaidPartial', '0.0002', Invoice::UNDERPAID, false],
    'overpaid' => ['Settled', 'PaidOver', '0.0005', Invoice::OVERPAID, true],
    'paid after expiry' => ['Expired', 'PaidLate', '0.00031666', Invoice::EXPIRED, false],
    'marked in BTCPay' => ['Settled', 'Marked', '0', Invoice::CONFIRMED, true],
]);

it('expires unpaid invoices quietly', function () {
    $invoice = buy($this->user, $this->starter);
    $this->btcpay->set($invoice->provider_invoice_id, 'Expired');

    $invoice = app(PaymentService::class)->refresh($invoice);
    expect($invoice->status)->toBe(Invoice::EXPIRED)->and($invoice->needs_review)->toBeFalse();
});

it('extends the period when the same plan is bought again', function () {
    $payments = app(PaymentService::class);
    $first = buy($this->user, $this->starter);
    $this->btcpay->set($first->provider_invoice_id, 'Settled', 'None', '0.00031666');
    $payments->refresh($first);

    $second = buy($this->user, $this->starter);
    $this->btcpay->set($second->provider_invoice_id, 'Settled', 'None', '0.00031666');
    $payments->refresh($second);

    $ends = Subscription::orderBy('id')->pluck('ends_at')->map->toDateTimeString()->all();
    expect($ends)->toBe(['2026-11-20 12:00:00', '2026-12-20 12:00:00'])
        ->and($this->user->activeSubscription()->ends_at->toDateTimeString())->toBe('2026-11-20 12:00:00');
});

it('reconciles open invoices when a webhook was missed', function () {
    $invoice = buy($this->user, $this->starter);
    $this->btcpay->set($invoice->provider_invoice_id, 'Settled', 'None', '0.00031666');

    $this->artisan('billing:reconcile --all')->expectsOutputToContain('1 changed')->assertSuccessful();

    expect($invoice->refresh()->status)->toBe(Invoice::CONFIRMED)->and(Subscription::count())->toBe(1);
});

it('keeps invoices private', function () {
    $invoice = buy($this->user, $this->starter);

    Livewire::actingAs(User::factory()->create())->test(InvoiceShow::class, ['invoice' => $invoice])->assertForbidden();
});

it('cannot buy the free plan or check out without configuration', function () {
    expect(fn () => buy($this->user, Plan::free()))->toThrow(RuntimeException::class);

    config(['services.btcpay.api_key' => null]);
    app()->forgetInstance(PaymentProvider::class);
    expect(fn () => app(PaymentService::class)->checkout($this->user, $this->starter))->toThrow(RuntimeException::class, 'not configured');
});

it('keeps payment secrets out of the logs', function () {
    $log = storage_path('logs/redaction-test.log');
    @unlink($log);
    config(['logging.channels.redaction' => ['driver' => 'single', 'path' => $log, 'tap' => [RedactSecrets::class]]]);

    logger()->channel('redaction')->error('provider said: bad key '.FakeBtcPay::API_KEY, ['secret' => FakeBtcPay::SECRET]);

    $content = file_get_contents($log);
    @unlink($log);
    expect($content)->toContain('[redacted]')->not->toContain(FakeBtcPay::API_KEY)->not->toContain(FakeBtcPay::SECRET);
});
