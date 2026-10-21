<article class="mx-auto max-w-[var(--content-max)] px-4 py-16 sm:px-6">
    <p class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[var(--tracking-caps)] text-primary"><span class="record"></span> For store owners</p>
    <h1 class="mt-4 text-4xl font-bold tracking-[var(--tracking-tight)]">How StoreSiphon treats your store</h1>
    <div class="mt-6 space-y-4 text-secondary">
        <p>StoreSiphon reads only catalogue data your store already makes public: product names, prices, stock status, images and descriptions. It never logs in, never touches carts, checkouts or customer data, and never tries to get around access controls, bot challenges or rate limits.</p>
        <ul class="space-y-2">
            <li class="flex gap-3"><span class="record mt-1.5"></span>We identify ourselves as <code class="font-mono text-sm text-ink">StoreSiphonBot</code> in the user agent.</li>
            <li class="flex gap-3"><span class="record mt-1.5"></span>We follow robots.txt, including <code class="font-mono text-sm text-ink">Crawl-delay</code>, and wait at least one second between requests to the same store.</li>
            <li class="flex gap-3"><span class="record mt-1.5"></span>If your server answers 401/403 or shows a challenge page, we stop.</li>
        </ul>
    </div>

    <h2 class="mt-12 text-2xl font-semibold">Exclude your store</h2>
    <p class="mt-3 text-secondary">The quickest way is robots.txt. It takes effect for every new extraction:</p>
    <pre class="mt-3 overflow-x-auto rounded-md bg-sunken p-4 font-mono text-sm text-ink">User-agent: StoreSiphonBot
Disallow: /</pre>
    <p class="mt-4 text-secondary">Or ask us to block your domain permanently. To make sure nobody can opt out a store they do not own, you confirm by publishing a short token on your domain.</p>

    <x-card class="mt-6">
        @if (! $request)
            <form wire:submit="submit" class="space-y-4">
                <x-input name="domain" wire:model="domain" label="Store domain" placeholder="shop.example.com" mono />
                <x-input name="email" type="email" wire:model="email" label="Your email" hint="Used only to reply about this request." />
                <x-turnstile wire:model="turnstileToken" />
                <x-button type="submit">Get verification token</x-button>
            </form>
        @else
            <div class="space-y-4">
                <p class="text-sm text-secondary">Create this file on <strong class="text-ink">{{ $request->host }}</strong>:</p>
                <p class="rounded-md bg-sunken p-3 font-mono text-sm text-ink break-all">https://{{ $request->host }}{{ $path }}</p>
                <p class="text-sm text-secondary">with this content:</p>
                <p class="rounded-md bg-sunken p-3 font-mono text-sm text-ink break-all" aria-label="Verification token">{{ $request->token }}</p>

                @if ($result === 'verified' || $request->verified_at)
                    <x-alert tone="success" title="Your store is excluded">StoreSiphon will no longer extract {{ $request->host }}. Any running extractions have been stopped. You can remove the file now.</x-alert>
                @else
                    @if ($result === 'not_found')
                        <x-alert tone="warning">We could not find the token yet. Check the file location and content, then try again.</x-alert>
                    @elseif ($result === 'too_many')
                        <x-alert tone="warning">Too many attempts. Please wait a few minutes.</x-alert>
                    @endif
                    <x-button wire:click="verify" wire:loading.attr="disabled">I've published it — verify</x-button>
                @endif
            </div>
        @endif
    </x-card>

    <p class="mt-8 text-sm text-muted">Questions or a problem with how we accessed your store? Write to <a class="text-primary hover:underline" href="mailto:stores@storesiphon.com">stores@storesiphon.com</a>.</p>
</article>
