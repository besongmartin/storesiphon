<x-layouts.auth heading="Two-factor authentication" subheading="Enter the 6-digit code from your authenticator app, or one of your recovery codes.">
    <form method="POST" action="{{ route('two-factor.login') }}" class="space-y-4" x-data="{ recovery: false }">
        @csrf
        <div x-show="! recovery">
            <x-input name="code" label="Authentication code" inputmode="numeric" autocomplete="one-time-code" mono autofocus />
        </div>
        <div x-cloak x-show="recovery">
            <x-input name="recovery_code" label="Recovery code" autocomplete="one-time-code" mono />
        </div>
        <x-button type="submit" class="w-full">Continue</x-button>
        <button type="button" class="w-full text-sm font-medium text-primary hover:underline" x-on:click="recovery = ! recovery"
            x-text="recovery ? 'Use an authentication code' : 'Use a recovery code'">Use a recovery code</button>
    </form>
</x-layouts.auth>
