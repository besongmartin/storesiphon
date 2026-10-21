<x-layouts.auth heading="Sign in" subheading="Welcome back to StoreSiphon.">
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <x-input name="email" type="email" label="Email" autocomplete="email" required autofocus />
        <x-input name="password" type="password" label="Password" autocomplete="current-password" required />
        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-secondary">
                <input type="checkbox" name="remember" class="size-4 rounded-xs border-line-strong accent-[var(--color-primary)]"> Remember me
            </label>
            <a href="{{ route('password.request') }}" class="text-sm font-medium text-primary hover:underline">Forgot password?</a>
        </div>
        <x-button type="submit" class="w-full">Sign in</x-button>
    </form>
    <x-slot:footer>New to StoreSiphon? <a href="{{ route('register') }}" class="font-medium text-primary hover:underline">Create an account</a></x-slot:footer>
</x-layouts.auth>
