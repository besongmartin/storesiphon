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
        <x-card title="Delete account">
            <p class="text-sm text-secondary">Deletes your account, extractions, products and export files immediately. Invoices are kept for accounting without your personal details. This cannot be undone.</p>
            <form method="POST" action="{{ route('settings.account.destroy') }}" class="mt-4 flex flex-wrap items-end gap-3"
                x-data x-on:submit="if (! confirm('Delete your StoreSiphon account and all its data?')) $event.preventDefault()">
                @csrf
                @method('DELETE')
                <div class="w-64"><x-input name="password" type="password" label="Confirm with your password" bag="deleteAccount" autocomplete="current-password" required /></div>
                <x-button type="submit" variant="danger">Delete my account</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.app>
