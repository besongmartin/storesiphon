{{-- Cloudflare Turnstile widget. With wire:model it reports its token to Livewire; in plain forms it adds
     the cf-turnstile-response field. Renders nothing until Turnstile keys are configured. --}}
@php($turnstile = app(\App\Domain\Security\Turnstile::class))
@if ($turnstile->enabled())
    @php($model = $attributes->wire('model')->value())
    <div class="space-y-1.5">
        <div class="cf-turnstile" wire:ignore x-data
            x-init="
                const render = () => window.turnstile.render($el, {
                    sitekey: @js($turnstile->siteKey()),
                    theme: document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light',
                    callback: (token) => { @if ($model) $wire.set(@js($model), token, false) @endif },
                });
                window.turnstile ? render() : window.addEventListener('turnstile-ready', render, { once: true });
            "></div>
        @if ($model)
            @error($model)<p class="text-xs text-danger">{{ $message }}</p>@enderror
        @endif
    </div>
    @once
        <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">window.onTurnstileLoad = () => window.dispatchEvent(new Event('turnstile-ready'));</script>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onTurnstileLoad&render=explicit" async defer nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}"></script>
    @endonce
@endif
