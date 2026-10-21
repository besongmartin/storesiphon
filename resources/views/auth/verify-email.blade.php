<x-layouts.auth heading="Verify your email" subheading="We sent a verification link to {{ auth()->user()->email }}. Open it to activate your account.">
    @if (session('status') === 'verification-link-sent')
        <x-alert tone="success" class="mb-4">A new verification link has been sent.</x-alert>
    @endif
    <div class="space-y-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-button type="submit" class="w-full">Resend verification email</x-button>
        </form>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-button type="submit" variant="ghost" class="w-full">Sign out</x-button>
        </form>
    </div>
</x-layouts.auth>
