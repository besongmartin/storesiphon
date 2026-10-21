<?php

use App\Domain\Compliance\OptOutService;
use App\Domain\Extraction\ExtractionService;
use App\Livewire\StoreOwners;
use App\Models\AuditLog;
use App\Models\Export;
use App\Models\ExtractionJob;
use App\Models\Invoice;
use App\Models\OptOutRequest;
use App\Models\Plan;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\FakeWooStore;

beforeEach(function () {
    Carbon::setTestNow('2026-10-21 12:00:00');
    Sleep::fake(syncWithCarbon: true);
});

/* ---------------------------------------------------------------- store-owner opt-out */

it('lets a store owner opt out by publishing a token on their domain', function () {
    $store = (new FakeWooStore)->install();
    $component = Livewire::test(StoreOwners::class)
        ->set('domain', 'https://shop.acme-store.com/')
        ->set('email', 'owner@acme-store.com')
        ->call('submit');

    $request = OptOutRequest::sole();
    expect($request->host)->toBe('shop.acme-store.com')->and($request->token)->toStartWith('storesiphon-optout-');
    $component->assertSee($request->token)->assertSee('/.well-known/storesiphon-optout.txt');

    // Not published yet
    $component->call('verify')->assertSee('could not find the token');
    expect(Store::where('host', 'shop.acme-store.com')->value('opted_out_at'))->toBeNull();

    // Published
    $store->on('/.well-known/storesiphon-optout.txt', Http::response("owner token\n{$request->token}\n", 200, ['Content-Type' => 'text/plain']));
    $component->call('verify')->assertSee('Your store is excluded');

    expect(Store::where('host', 'shop.acme-store.com')->first()->isOptedOut())->toBeTrue()
        ->and(AuditLog::where('action', 'store.opted_out')->count())->toBe(1);

    Queue::fake();
    expect(fn () => app(ExtractionService::class)->start(User::factory()->create(), 'shop.acme-store.com'))
        ->toThrow(ValidationException::class, 'asked StoreSiphon not to extract');
});

it('treats a robots.txt block on StoreSiphonBot as a verified opt-out and stops running jobs', function () {
    (new FakeWooStore)->robots("User-agent: StoreSiphonBot\nDisallow: /\n")->install();
    $request = app(OptOutService::class)->request('shop.acme-store.com', 'owner@acme-store.com');
    $storeRow = Store::create(['host' => 'shop.acme-store.com', 'origin' => FakeWooStore::ORIGIN]);
    $running = ExtractionJob::create(['user_id' => User::factory()->create()->id, 'input_url' => FakeWooStore::ORIGIN.'/', 'status' => ExtractionJob::RUNNING, 'options' => []]);
    $running->forceFill(['store_id' => $storeRow->id])->save();

    expect(app(OptOutService::class)->verify($request))->toBeTrue()
        ->and($running->refresh()->cancel_requested_at)->not->toBeNull();
});

it('cannot opt out a store with a token someone else wrote', function () {
    (new FakeWooStore)->on('/.well-known/storesiphon-optout.txt', Http::response('storesiphon-optout-someoneelse', 200))->install();
    $request = app(OptOutService::class)->request('shop.acme-store.com', 'attacker@example.org');

    expect(app(OptOutService::class)->verify($request))->toBeFalse()
        ->and(Store::where('host', 'shop.acme-store.com')->value('opted_out_at'))->toBeNull();
});

/* ---------------------------------------------------------------- retention */

it('purges expired extractions, their products and export files', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $expired = ExtractionJob::create(['user_id' => $user->id, 'input_url' => 'https://a-store.com/', 'status' => ExtractionJob::SUCCEEDED, 'options' => [], 'expires_at' => now()->subHour()]);
    $fresh = ExtractionJob::create(['user_id' => $user->id, 'input_url' => 'https://a-store.com/', 'status' => ExtractionJob::SUCCEEDED, 'options' => [], 'expires_at' => now()->addDay()]);
    $runningButOld = ExtractionJob::create(['user_id' => $user->id, 'input_url' => 'https://a-store.com/', 'status' => ExtractionJob::RUNNING, 'options' => [], 'expires_at' => now()->subHour()]);
    DB::table('products')->insert(['extraction_job_id' => $expired->id, 'external_id' => '1', 'type' => 'simple', 'name' => 'x', 'permalink' => 'https://a-store.com/p/', 'stock_status' => 'instock', 'method' => 'store_api', 'content_hash' => str_repeat('a', 64)]);
    $export = Export::create(['extraction_job_id' => $expired->id, 'format' => 'csv', 'status' => Export::READY]);
    $export->forceFill(['disk' => 'local', 'path' => "exports/{$expired->id}/{$export->id}.csv"])->save();
    Storage::disk('local')->put($export->path, 'data');
    $oldOptOut = OptOutRequest::create(['host' => 'a-store.com', 'email' => 'x@y.z', 'token' => 't1']);
    $oldOptOut->forceFill(['created_at' => now()->subDays(31)])->save();

    $this->artisan('storesiphon:purge --dry-run')->assertSuccessful();
    expect(ExtractionJob::count())->toBe(3);

    $this->artisan('storesiphon:purge')->expectsOutputToContain('Purged 1 extraction(s) with 1 product row(s)')->assertSuccessful();

    expect(ExtractionJob::pluck('id')->sort()->values()->all())->toBe(collect([$fresh->id, $runningButOld->id])->sort()->values()->all())
        ->and(DB::table('products')->count())->toBe(0)
        ->and(Export::count())->toBe(0)
        ->and(Storage::disk('local')->exists($export->path))->toBeFalse()
        ->and(OptOutRequest::count())->toBe(0);
});

/* ---------------------------------------------------------------- security headers & Turnstile */

it('sends security headers with a per-request script nonce', function () {
    $response = $this->get('/');
    $csp = $response->headers->get('Content-Security-Policy');

    preg_match("/'nonce-([^']+)'/", $csp, $m);
    expect($csp)->toContain("frame-ancestors 'none'")->toContain("object-src 'none'")->toContain("default-src 'self'")
        ->and($m[1] ?? null)->not->toBeEmpty();
    $response->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertSee('<script nonce="'.$m[1].'">', false);
});

it('requires a valid Turnstile token on registration when Turnstile is configured', function () {
    config(['services.turnstile' => ['site_key' => 'site-key', 'secret_key' => 'secret-key']]);
    Http::fake(['https://challenges.cloudflare.com/*' => fn (Request $r) => Http::response(['success' => $r['response'] === 'good-token'])]);
    $form = ['name' => 'Bot', 'email' => 'bot@example.org', 'password' => 'correct-horse-battery', 'password_confirmation' => 'correct-horse-battery'];

    $this->get('/register')->assertSee('class="cf-turnstile"', false)->assertSee('challenges.cloudflare.com/turnstile', false);
    $this->post('/register', $form)->assertSessionHasErrors('email');
    $this->post('/register', $form + ['cf-turnstile-response' => 'bad-token'])->assertSessionHasErrors('email');
    expect(User::count())->toBe(0);

    $this->post('/register', $form + ['cf-turnstile-response' => 'good-token'])->assertRedirect('/dashboard');
    expect(User::count())->toBe(1);
});

/* ---------------------------------------------------------------- account deletion */

it('deletes an account and its data but keeps anonymised invoices', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $job = ExtractionJob::create(['user_id' => $user->id, 'input_url' => 'https://a-store.com/', 'status' => ExtractionJob::SUCCEEDED, 'options' => []]);
    Storage::disk('local')->put("exports/{$job->id}/file.csv", 'data');
    $invoice = Invoice::create(['user_id' => $user->id, 'plan_id' => Plan::where('slug', 'starter')->value('id'), 'provider' => 'btcpay', 'fiat_amount' => 19, 'fiat_currency' => 'USD', 'status' => Invoice::CONFIRMED]);

    $this->actingAs($user)->delete('/settings/account', ['password' => 'wrong'])->assertSessionHasErrorsIn('deleteAccount', 'password');
    expect(User::count())->toBe(1);

    $this->actingAs($user)->delete('/settings/account', ['password' => 'password'])->assertRedirect('/');

    $this->assertGuest();
    expect(User::find($user->id))->toBeNull()
        ->and(ExtractionJob::count())->toBe(0)
        ->and(Storage::disk('local')->exists("exports/{$job->id}/file.csv"))->toBeFalse()
        ->and($invoice->refresh()->user_id)->toBeNull()
        ->and(AuditLog::where('action', 'user.deleted')->count())->toBe(1);
});

/* ---------------------------------------------------------------- legal & health */

it('serves complete legal drafts', function (string $doc, string $needle) {
    $this->get("/legal/{$doc}")->assertOk()->assertSee('pending legal review')->assertSee($needle);
})->with([
    ['terms', 'Acceptable use'],
    ['privacy', 'Your rights'],
    ['refunds', 'Underpayment'],
]);

it('reports health and fails when heartbeats are missing', function () {
    $this->artisan('storesiphon:health')->assertFailed();

    cache()->put('heartbeat:worker', now()->toIso8601String());
    cache()->put('heartbeat:scheduler', now()->toIso8601String());
    $this->artisan('storesiphon:health')->assertSuccessful();

    $this->get('/up')->assertOk();
});
