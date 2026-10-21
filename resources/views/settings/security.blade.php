@php
    $user = auth()->user();
    $enabled = ! is_null($user->two_factor_secret);
    $confirmed = ! is_null($user->two_factor_confirmed_at);
    $showCodes = $confirmed && in_array(session('status'), ['two-factor-authentication-confirmed', 'recovery-codes-generated'], true);
@endphp
<x-layouts.app title="Settings">
    <div class="max-w-[var(--content-max)] space-y-6">
        @include('settings._nav')

        <x-card title="Two-factor authentication">
            <x-slot:actions>
                @if ($confirmed)
                    <x-badge tone="success">Enabled</x-badge>
                @elseif ($enabled)
                    <x-badge tone="warning">Setup pending</x-badge>
                @else
                    <x-badge>Off</x-badge>
                @endif
            </x-slot:actions>

            @if (! $enabled)
                <p class="text-sm text-secondary">Add a second step at sign-in using an authenticator app (1Password, Authy, Google Authenticator…). Required for administrator accounts.</p>
                <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-4">
                    @csrf
                    <x-button type="submit">Enable two-factor</x-button>
                </form>
            @elseif (! $confirmed)
                <p class="text-sm text-secondary">Scan this QR code with your authenticator app, then enter the 6-digit code to finish setup.</p>
                <div class="mt-4 flex flex-wrap items-start gap-6">
                    <div class="rounded-md bg-white p-3" aria-label="Two-factor QR code">{!! $user->twoFactorQrCodeSvg() !!}</div>
                    <div class="space-y-2 text-sm">
                        <p class="text-muted">Can't scan? Enter this key manually:</p>
                        <p class="font-mono text-ink break-all">{{ decrypt($user->two_factor_secret) }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-6 max-w-xs space-y-4">
                    @csrf
                    <x-input name="code" label="Authentication code" bag="confirmTwoFactorAuthentication" inputmode="numeric" autocomplete="one-time-code" mono required />
                    <div class="flex gap-2">
                        <x-button type="submit">Confirm</x-button>
                    </div>
                </form>
                <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-3">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" variant="ghost" size="sm">Cancel setup</x-button>
                </form>
            @else
                <p class="text-sm text-secondary">Two-factor authentication is on. Keep your recovery codes somewhere safe; each one works once.</p>
                @if ($showCodes)
                    <ul class="mt-4 grid grid-cols-2 gap-2 rounded-md bg-sunken p-4 font-mono text-sm" aria-label="Recovery codes">
                        @foreach ($user->recoveryCodes() as $code)
                            <li>{{ $code }}</li>
                        @endforeach
                    </ul>
                @endif
                <div class="mt-4 flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
                        @csrf
                        <x-button type="submit" variant="secondary">{{ $showCodes ? 'Regenerate codes' : 'Show new recovery codes' }}</x-button>
                    </form>
                    <form method="POST" action="{{ route('two-factor.disable') }}">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="danger">Turn off</x-button>
                    </form>
                </div>
            @endif
        </x-card>
    </div>
</x-layouts.app>
