<?php

use App\Domain\Billing\PaymentService;
use App\Domain\Extraction\ExtractionService;
use App\Livewire\Admin\Payments;
use App\Models\AuditLog;
use App\Models\ExtractionJob;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\FakeBtcPay;

beforeEach(function () {
    Carbon::setTestNow('2026-10-21 12:00:00');
    Queue::fake();
});

function subscribe(User $user, string $slug): Subscription
{
    return Subscription::create([
        'user_id' => $user->id, 'plan_id' => Plan::where('slug', $slug)->value('id'),
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(29), 'source' => 'admin',
    ]);
}

function admin(bool $twoFactor = true): User
{
    $admin = User::factory()->create();
    $admin->forceFill(['role' => User::ROLE_ADMIN, 'two_factor_confirmed_at' => $twoFactor ? now() : null])->save();

    return $admin;
}

it('applies the paid plan limits to new extractions', function () {
    $user = User::factory()->create();
    expect(app(ExtractionService::class)->start($user, 'shop.acme-store.com', ['max_products' => 999])->maxProducts())->toBe(25);

    ExtractionJob::query()->update(['status' => ExtractionJob::SUCCEEDED, 'products_found' => 25]);
    subscribe($user, 'starter');
    $job = app(ExtractionService::class)->start($user, 'shop.acme-store.com', ['max_products' => 999]);

    expect($user->currentPlan()->slug)->toBe('starter')
        ->and($job->maxProducts())->toBe(999)
        ->and($job->expires_at->toDateString())->toBe('2026-11-20');
});

it('stops the Free plan at its period quota', function () {
    $user = User::factory()->create();
    ExtractionJob::create([
        'user_id' => $user->id, 'input_url' => 'https://shop.acme-store.com/', 'status' => ExtractionJob::SUCCEEDED,
        'options' => ['max_products' => 25], 'products_found' => Plan::free()->product_quota,
    ])->forceFill(['products_found' => Plan::free()->product_quota])->save();

    expect(fn () => app(ExtractionService::class)->start($user, 'shop.acme-store.com'))
        ->toThrow(ValidationException::class, 'Upgrade to keep extracting');
});

it('caps a job at the products left in the period', function () {
    $user = User::factory()->create();
    ExtractionJob::create([
        'user_id' => $user->id, 'input_url' => 'https://shop.acme-store.com/', 'status' => ExtractionJob::SUCCEEDED, 'options' => [],
    ])->forceFill(['products_found' => Plan::free()->product_quota - 10])->save();

    expect(app(ExtractionService::class)->start($user, 'shop.acme-store.com')->maxProducts())->toBe(10);
});

it('shows the billing page with plans and usage', function () {
    $this->actingAs(User::factory()->create())->get('/billing')
        ->assertOk()->assertSee('Starter')->assertSee('Pro')->assertSee('Pay with Bitcoin')->assertSee('not configured');
});

it('keeps the admin area to admins with two-factor authentication', function () {
    $this->actingAs(User::factory()->create())->get('/admin/payments')->assertForbidden();
    $this->actingAs(admin(twoFactor: false))->get('/admin/payments')->assertRedirect('/settings/security');
    $this->actingAs(admin())->get('/admin/payments')->assertOk()->assertSee('Needs review');
});

it('lets an admin resolve a flagged invoice with a note, recorded in the audit log', function () {
    $btcpay = (new FakeBtcPay)->install();
    $customer = User::factory()->create();
    $invoice = app(PaymentService::class)->checkout($customer, Plan::where('slug', 'starter')->sole());
    $btcpay->set($invoice->provider_invoice_id, 'Expired', 'PaidPartial', '0.0002');
    app(PaymentService::class)->refresh($invoice);
    $admin = admin();

    Livewire::actingAs($admin)->test(Payments::class)
        ->assertSee($customer->email)
        ->call('select', $invoice->id)
        ->call('resolve', $invoice->id, true)->assertHasErrors(['note'])
        ->set('note', 'Customer paid the remaining amount separately (txid abc123).')
        ->call('resolve', $invoice->id, true)->assertHasNoErrors();

    $invoice->refresh();
    expect($invoice->needs_review)->toBeFalse()
        ->and($invoice->granted_at)->not->toBeNull()
        ->and(Subscription::where('invoice_id', $invoice->id)->sole()->source)->toBe('admin')
        ->and(AuditLog::where('action', 'invoice.resolved_granted')->sole()->actor_id)->toBe($admin->id)
        ->and($customer->currentPlan()->slug)->toBe('starter');

    // Resolving again can never grant a second period.
    app(PaymentService::class)->resolve($invoice, $admin, 'double click', true);
    expect(Subscription::where('invoice_id', $invoice->id)->count())->toBe(1);
});
