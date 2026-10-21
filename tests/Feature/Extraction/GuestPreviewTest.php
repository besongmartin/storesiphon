<?php

use App\Jobs\RunExtraction;
use App\Livewire\TryIt;
use App\Models\ExtractionJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Livewire\Livewire;
use Tests\Support\FakeWooStore;

beforeEach(function () {
    Carbon::setTestNow('2026-10-21 12:00:00');
    Sleep::fake(syncWithCarbon: true);
});

it('serves the try-it page to visitors', function () {
    $this->get('/try')->assertOk()->assertSee('See what StoreSiphon finds');
});

it('runs a small anonymous preview bound to the visitor session', function () {
    (new FakeWooStore)->install(); // queue is sync in tests: the job runs immediately

    $component = Livewire::test(TryIt::class)->set('url', 'shop.acme-store.com')->call('start');

    $job = ExtractionJob::sole();
    expect($job->user_id)->toBeNull()
        ->and($job->guest_token)->toBe(ExtractionJob::guestTokenHash(session('guest_token')))
        ->and($job->maxProducts())->toBe(10)
        ->and($job->status)->toBe(ExtractionJob::SUCCEEDED);

    $component->assertSee('Wool Beanie – Winter')->assertSee('Create a free account')->assertDontSee('Download');
});

it('hides a guest job from other sessions', function () {
    $job = ExtractionJob::create([
        'guest_token' => ExtractionJob::guestTokenHash('someone-elses-token'),
        'input_url' => FakeWooStore::ORIGIN.'/', 'status' => ExtractionJob::SUCCEEDED, 'options' => ['max_products' => 10],
    ]);

    session(['guest_token' => 'my-token', 'guest_job' => $job->id]);
    Livewire::test(TryIt::class)->assertSee('Preview store')->assertDontSee('shop.acme-store.com');
});

it('rate limits anonymous previews per IP', function () {
    Queue::fake();
    $component = Livewire::test(TryIt::class);

    foreach (range(1, 3) as $_) {
        $component->set('url', 'shop.acme-store.com')->call('start')->assertHasNoErrors()->call('startOver');
    }
    $component->set('url', 'shop.acme-store.com')->call('start')->assertHasErrors(['url']);

    Queue::assertPushed(RunExtraction::class, 3);
});
