<x-layouts.app title="Settings">
    <div class="max-w-[var(--content-max)] space-y-6">
        @include('settings._nav')

        <x-card title="Profile">
            <form method="POST" action="{{ route('user-profile-information.update') }}" class="space-y-4">
                @csrf
                @method('PUT')
                <x-input name="name" label="Name" bag="updateProfileInformation" :value="auth()->user()->name" autocomplete="name" required />
                <x-input name="email" type="email" label="Email" bag="updateProfileInformation" :value="auth()->user()->email" autocomplete="email"
                    hint="Changing your email requires verifying the new address." required />
                <x-button type="submit">Save profile</x-button>
            </form>
        </x-card>

        <x-card title="Password">
            <form method="POST" action="{{ route('user-password.update') }}" class="space-y-4">
                @csrf
                @method('PUT')
                <x-input name="current_password" type="password" label="Current password" bag="updatePassword" autocomplete="current-password" required />
                <x-input name="password" type="password" label="New password" bag="updatePassword" autocomplete="new-password" required />
                <x-input name="password_confirmation" type="password" label="Confirm new password" bag="updatePassword" autocomplete="new-password" required />
                <x-button type="submit">Update password</x-button>
            </form>
        </x-card>

        <x-card title="Appearance">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <p class="text-sm text-secondary">Choose a theme for this browser. System follows your device setting.</p>
                <x-theme-switcher />
            </div>
        </x-card>
    </div>
</x-layouts.app>
