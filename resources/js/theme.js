// Theme preference: 'system' | 'light' | 'dark', stored per browser.
// The resolved theme is written to <html data-theme> (an inline copy of resolve() runs in <head> to avoid a flash).
const KEY = 'ss-theme';

export function preference() {
    try {
        return localStorage.getItem(KEY) || 'system';
    } catch {
        return 'system';
    }
}

export function resolve(pref = preference()) {
    if (pref === 'light' || pref === 'dark') return pref;
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function apply(pref = preference()) {
    document.documentElement.dataset.theme = resolve(pref);
}

export function set(pref) {
    try {
        if (pref === 'system') localStorage.removeItem(KEY);
        else localStorage.setItem(KEY, pref);
    } catch {
        /* storage unavailable: still apply for this page */
    }
    apply(pref);
}

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (preference() === 'system') apply('system');
});
