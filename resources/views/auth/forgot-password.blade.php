<x-layouts.auth heading="Reset your password" subheading="We'll email you a link to choose a new password.">
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <x-input name="email" type="email" label="Email" autocomplete="email" required autofocus />
        <x-button type="submit" class="w-full">Email reset link</x-button>
    </form>
    <x-slot:footer><a href="{{ route('login') }}" class="font-medium text-primary hover:underline">Back to sign in</a></x-slot:footer>
</x-layouts.auth>
