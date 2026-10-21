import * as theme from './theme';

window.ssTheme = theme;

// Alpine is bundled with Livewire 3; register small UI helpers before it starts.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('themeSwitcher', () => ({
        pref: theme.preference(),
        choose(value) {
            this.pref = value;
            theme.set(value);
        },
    }));
});
