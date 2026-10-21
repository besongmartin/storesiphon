<?php

it('renders the landing page with the brand and pricing', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Point it at a store. Get the catalogue, structured.')
        ->assertSee('/brand/', false)
        ->assertSee('id="pricing"', false)
        ->assertSee('Start free');
});

it('plays the logo intro only on the first page of a session', function () {
    $this->get('/')->assertSee('storesiphon-horizontal-animated.svg', false);
    $this->get('/')->assertDontSee('storesiphon-horizontal-animated.svg', false)
        ->assertSee('storesiphon-horizontal-color.svg', false);
});

it('serves the draft legal pages', function (string $document) {
    $this->get("/legal/{$document}")->assertOk()->assertSee('pending legal review');
})->with(['terms', 'privacy', 'refunds']);

it('returns 404 for unknown legal documents', function () {
    $this->get('/legal/anything-else')->assertNotFound();
});

it('includes favicon, manifest and theme bootstrapping in the head', function () {
    $this->get('/')
        ->assertSee('/brand/icons/favicon.svg', false)
        ->assertSee('/brand/icons/site.webmanifest', false)
        ->assertSee("localStorage.getItem('ss-theme')", false);
});
