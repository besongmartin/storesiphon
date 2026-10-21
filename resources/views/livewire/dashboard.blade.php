<div class="space-y-8">
    <div>
        <h2 class="text-2xl font-semibold tracking-[var(--tracking-tight)]">Welcome, {{ auth()->user()->name }}</h2>
        <p class="mt-1 text-sm text-muted">Point StoreSiphon at a WooCommerce store to get its catalogue as structured data.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        @foreach ($stats as $stat)
            <div class="rounded-lg border border-line bg-surface p-5">
                <p class="text-xs font-semibold uppercase tracking-[var(--tracking-caps)] text-muted">{{ $stat['label'] }}</p>
                <p class="mt-2 text-3xl font-semibold tabular">{{ $stat['value'] }}</p>
                <p class="mt-1 text-xs text-muted">{{ $stat['hint'] }}</p>
            </div>
        @endforeach
    </div>

    <x-card title="Recent extractions">
        <div class="flex flex-col items-center py-10 text-center">
            <span class="size-4 rounded-xs bg-logo-record" aria-hidden="true"></span>
            <p class="mt-4 font-medium">No extractions yet</p>
            <p class="mt-1 max-w-sm text-sm text-muted">Extraction jobs arrive in an upcoming release. Your account, sign-in and settings are ready.</p>
            <x-button class="mt-6" disabled>New extraction</x-button>
        </div>
    </x-card>
</div>
