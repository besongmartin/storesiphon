<?php

use App\Domain\Extraction\ExtractionService;
use App\Domain\Extraction\JobRunner;
use App\Jobs\RunExtraction;
use App\Livewire\Extractions\Create;
use App\Livewire\Extractions\Show;
use App\Models\ExtractionJob;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\FakeWooStore;

beforeEach(function () {
    Carbon::setTestNow('2026-10-21 12:00:00');
    Sleep::fake(syncWithCarbon: true);
});

function queuedJob(User $user, array $options = []): ExtractionJob
{
    return ExtractionJob::create([
        'user_id' => $user->id,
        'input_url' => FakeWooStore::ORIGIN.'/',
        'status' => ExtractionJob::QUEUED,
        'options' => $options + ['max_products' => 25, 'include_variations' => true],
        'expires_at' => now()->addDays(7),
    ]);
}

it('starts an extraction from the form and queues it', function () {
    Queue::fake();
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Create::class)
        ->set('url', 'shop.acme-store.com')
        ->set('maxProducts', 10)
        ->call('start')
        ->assertRedirect();

    $job = ExtractionJob::sole();
    expect($job->user_id)->toBe($user->id)
        ->and($job->input_url)->toBe('https://shop.acme-store.com/')
        ->and($job->options)->toBe(['max_products' => 10, 'include_variations' => true])
        ->and($job->status)->toBe(ExtractionJob::QUEUED);
    Queue::assertPushed(RunExtraction::class, fn ($q) => $q->jobId === $job->id);
});

it('caps the product limit and refuses unsafe addresses', function () {
    Queue::fake();
    $user = User::factory()->create();

    $job = app(ExtractionService::class)->start($user, 'shop.acme-store.com', ['max_products' => 5000]);
    expect($job->maxProducts())->toBe(config('storesiphon.limits.free.max_products_per_job'));

    $job->forceFill(['status' => ExtractionJob::SUCCEEDED])->save();
    expect(fn () => app(ExtractionService::class)->start($user, 'http://localhost:8080/'))->toThrow(ValidationException::class);
    expect(fn () => app(ExtractionService::class)->start($user, 'http://192.168.1.10/'))->toThrow(ValidationException::class);
});

it('allows only one running extraction on the Free plan', function () {
    Queue::fake();
    $user = User::factory()->create();
    app(ExtractionService::class)->start($user, 'shop.acme-store.com');

    expect(fn () => app(ExtractionService::class)->start($user, 'shop.acme-store.com'))
        ->toThrow(ValidationException::class, 'already have an extraction running');
});

it('runs an extraction end to end and stores products with their variations', function () {
    (new FakeWooStore)->install();
    $job = queuedJob(User::factory()->create());

    (new RunExtraction($job->id))->handle(app(JobRunner::class));
    $job->refresh();

    expect($job->status)->toBe(ExtractionJob::SUCCEEDED)
        ->and($job->products_found)->toBe(4)
        ->and($job->variations_found)->toBe(2)
        ->and($job->methods_used)->toBe('store_api')
        ->and($job->products_estimated)->toBe(4)
        ->and($job->preflight['currency'])->toBe('EUR')
        ->and($job->started_at)->not->toBeNull()
        ->and($job->finished_at)->not->toBeNull()
        ->and($job->store->host)->toBe('shop.acme-store.com')
        ->and($job->store->name)->toBe('Acme Hats');

    $cap = Product::where('extraction_job_id', $job->id)->where('sku', 'CAP-VAR')->sole();
    expect($cap->variations->pluck('sku')->all())->toBe(['CAP-RED', 'CAP-BLUE'])
        ->and((string) $cap->variations[1]->sale_price)->toBe('11.000000')
        ->and($cap->variations[1]->variation_attributes)->toBe(['Colour' => 'Blue']);
    expect(Product::where('extraction_job_id', $job->id)->whereNull('parent_id')->orderBy('position')->pluck('sku')->all())
        ->toBe(['HAT-WOOL', 'HAT-STRAW', 'CAP-VAR', 'HOOD-1']);
});

it('stops within one request when the user cancels', function () {
    $user = User::factory()->create();
    $job = queuedJob($user);
    $store = (new FakeWooStore)->on('/wp-json/wc/store/v1/products/104', function () use ($job) {
        $job->forceFill(['cancel_requested_at' => now()])->save(); // user clicks Cancel during the run

        return Http::response(json_decode(FakeWooStore::fixture('store-api-variation-104.json'), true), 200);
    })->withoutBulkVariations()->install();

    app(JobRunner::class)->run($job);

    expect($job->refresh()->status)->toBe(ExtractionJob::CANCELLED)
        ->and($store->wasRequested('/products/105'))->toBeFalse()
        ->and($store->wasRequested('page=2'))->toBeFalse();
});

it('fetches variations for a group of products in one request', function () {
    $store = (new FakeWooStore)->install();
    $job = queuedJob(User::factory()->create());

    app(JobRunner::class)->run($job);

    $variationRequests = array_filter($store->requested, fn ($u) => str_contains($u, 'type=variation') || preg_match('#/products/\d+$#', $u));
    expect($job->refresh()->variations_found)->toBe(2)
        ->and($variationRequests)->toHaveCount(1)
        ->and($store->wasRequested('/products/104'))->toBeFalse();
});

it('falls back to individual variation requests when a store ignores the bulk filter', function () {
    $store = (new FakeWooStore)->withoutBulkVariations()->install();
    $job = queuedJob(User::factory()->create());

    app(JobRunner::class)->run($job);

    $bulk = array_filter($store->requested, fn ($u) => str_contains($u, 'type=variation'));
    expect($job->refresh()->variations_found)->toBe(2)
        ->and($bulk)->toHaveCount(1)                                  // tried once, did not page on
        ->and($store->wasRequested('/products/104'))->toBeTrue()
        ->and($store->wasRequested('/products/105'))->toBeTrue();
    expect(Product::where('extraction_job_id', $job->id)->where('sku', 'CAP-BLUE')->value('sale_price'))->toBe('11.000000');
});

it('cancels from the job page only for the owner', function () {
    $owner = User::factory()->create();
    $job = queuedJob($owner);

    Livewire::actingAs($owner)->test(Show::class, ['job' => $job])->call('cancel');
    expect($job->refresh()->cancel_requested_at)->not->toBeNull();

    $other = User::factory()->create();
    Livewire::actingAs($other)->test(Show::class, ['job' => $job])->assertForbidden();
});

it('records why a blocked store could not be extracted', function () {
    (new FakeWooStore)->on('/', fn ($r) => parse_url($r->url(), PHP_URL_PATH) === '/' ? Http::response('no', 403) : null)->install();
    $job = queuedJob(User::factory()->create());

    app(JobRunner::class)->run($job);

    expect($job->refresh()->status)->toBe(ExtractionJob::BLOCKED)
        ->and($job->error_code)->toBe('access_denied')
        ->and($job->error_message)->toContain('does not bypass access controls');
});

it('respects store owners who opted out', function () {
    Queue::fake();
    Store::create(['host' => 'acme-store.com', 'origin' => 'https://acme-store.com'])->forceFill(['opted_out_at' => now()])->save();

    expect(fn () => app(ExtractionService::class)->start(User::factory()->create(), 'https://www.acme-store.com/'))
        ->toThrow(ValidationException::class, 'asked StoreSiphon not to extract');
});

it('keeps every user\'s jobs private', function () {
    $owner = User::factory()->create();
    $job = queuedJob($owner);
    $other = User::factory()->create();

    $this->actingAs($other)->get(route('extractions.show', $job))->assertForbidden();
    $this->actingAs($other)->get(route('extractions.index'))->assertDontSee($job->id);
    $this->actingAs($owner)->get(route('extractions.show', $job))->assertOk();
});

it('never exposes guest jobs through signed-in pages', function () {
    $guestJob = ExtractionJob::create([
        'guest_token' => ExtractionJob::guestTokenHash('secret'),
        'input_url' => FakeWooStore::ORIGIN.'/', 'status' => ExtractionJob::SUCCEEDED,
        'options' => ['max_products' => 10],
    ]);

    $this->actingAs(User::factory()->create())->get(route('extractions.show', $guestJob))->assertForbidden();
});

it('shows progress, filters and searches the preview table', function () {
    (new FakeWooStore)->install();
    $user = User::factory()->create();
    $job = queuedJob($user);
    app(JobRunner::class)->run($job);

    Livewire::actingAs($user)->test(Show::class, ['job' => $job->refresh()])
        ->assertSee('Wool Beanie – Winter')
        ->assertSee('Baseball Cap')
        ->set('search', 'CAP-BLUE')->assertSee('Baseball Cap')->assertDontSee('Straw Hat')
        ->set('search', '')->set('stock', 'outofstock')->assertSee('Straw Hat')->assertDontSee('Wool Beanie')
        ->set('stock', '')->set('type', 'variable')->assertSee('Baseball Cap')->assertDontSee('Rain Hood');
});
