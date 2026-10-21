<x-layouts.auth heading="Confirm your password" subheading="This is a secure area. Please confirm your password to continue.">
    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
        @csrf
        <x-input name="password" type="password" label="Password" autocomplete="current-password" required autofocus />
        <x-button type="submit" class="w-full">Confirm</x-button>
    </form>
</x-layouts.auth>
