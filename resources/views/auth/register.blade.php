<x-layouts.auth heading="Create your account" subheading="Free plan included. No card or wallet needed.">
    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <x-input name="name" label="Name" autocomplete="name" required autofocus />
        <x-input name="email" type="email" label="Email" autocomplete="email" required />
        <x-input name="password" type="password" label="Password" autocomplete="new-password" hint="At least 8 characters." required />
        <x-input name="password_confirmation" type="password" label="Confirm password" autocomplete="new-password" required />
        <p class="text-xs text-muted">By creating an account you agree to the <a class="text-primary hover:underline" href="{{ route('legal', 'terms') }}">Terms</a> and acknowledge the <a class="text-primary hover:underline" href="{{ route('legal', 'privacy') }}">Privacy policy</a>.</p>
        <x-button type="submit" class="w-full">Create account</x-button>
    </form>
    <x-slot:footer>Already have an account? <a href="{{ route('login') }}" class="font-medium text-primary hover:underline">Sign in</a></x-slot:footer>
</x-layouts.auth>
