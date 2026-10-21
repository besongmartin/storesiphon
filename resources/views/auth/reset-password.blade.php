<x-layouts.auth heading="Choose a new password">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-input name="email" type="email" label="Email" autocomplete="email" :value="$request->email" required />
        <x-input name="password" type="password" label="New password" autocomplete="new-password" required autofocus />
        <x-input name="password_confirmation" type="password" label="Confirm new password" autocomplete="new-password" required />
        <x-button type="submit" class="w-full">Reset password</x-button>
    </form>
</x-layouts.auth>
