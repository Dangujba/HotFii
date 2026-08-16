import './bootstrap';
import 'bootstrap';
import 'admin-lte';
import ApexCharts from 'apexcharts';
window.ApexCharts = ApexCharts;
import './echo';

/* ───────────────────────────────────────────────
   HotFii Theme Switcher
   Cycles: daylight → dusk → midnight → daylight
   Persists to localStorage, falls back to system pref
   ─────────────────────────────────────────────── */
const ThemeSwitcher = (() => {
    const STORAGE_KEY = 'hotfii-theme';
    const THEMES = ['daylight', 'dusk', 'midnight'];
    const ICONS = {
        daylight: 'bi-sun',
        dusk:     'bi-sunset',
        midnight: 'bi-moon-stars',
    };
    // Bootstrap's data-bs-theme mapping
    const BS_MAP = {
        daylight: 'light',
        dusk:     'dark',
        midnight: 'dark',
    };

    function detect() {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved && THEMES.includes(saved)) return saved;
        // Infer from OS preference
        if (window.matchMedia?.('(prefers-color-scheme: dark)').matches) return 'midnight';
        return 'daylight';
    }

    function apply(theme) {
        const html = document.documentElement;
        html.setAttribute('data-hotfii-theme', theme);
        html.setAttribute('data-bs-theme', BS_MAP[theme]);
        localStorage.setItem(STORAGE_KEY, theme);

        // Update toggle button icon
        const btn = document.getElementById('theme-toggle');
        if (btn) {
            const icon = btn.querySelector('i');
            if (icon) {
                // Remove all theme icon classes
                icon.classList.remove(...Object.values(ICONS));
                icon.classList.add(ICONS[theme]);
            }
            // Update title
            btn.title = `Theme: ${theme.charAt(0).toUpperCase() + theme.slice(1)}`;
        }

        // Fire a custom event so charts / widgets can re-render
        window.dispatchEvent(new CustomEvent('theme-changed', { detail: { theme } }));
    }

    function cycle() {
        const current = document.documentElement.getAttribute('data-hotfii-theme') || 'daylight';
        const idx = THEMES.indexOf(current);
        const next = THEMES[(idx + 1) % THEMES.length];
        apply(next);
    }

    function init() {
        apply(detect());

        // Listen for OS preference changes
        window.matchMedia?.('(prefers-color-scheme: dark)')
            .addEventListener('change', (e) => {
                // Only auto-switch if user hasn't explicitly chosen
                if (!localStorage.getItem(STORAGE_KEY)) {
                    apply(e.matches ? 'midnight' : 'daylight');
                }
            });
    }

    return { init, apply, cycle, detect, THEMES };
})();

// Apply theme as early as possible (before DOMContentLoaded)
ThemeSwitcher.init();
window.HotFiiTheme = ThemeSwitcher;

document.addEventListener('DOMContentLoaded', () => {
    // Wire up the theme toggle button
    const toggle = document.getElementById('theme-toggle');
    if (toggle) {
        toggle.addEventListener('click', () => ThemeSwitcher.cycle());
    }

    // Confirm dialog handler
    document.querySelectorAll('[data-confirm]').forEach((element) => {
        element.addEventListener('click', (event) => {
            if (!window.confirm(element.dataset.confirm)) event.preventDefault();
        });
    });

    // Livewire real-time channel listeners
    const organization = document.body.dataset.organizationUuid;
    if (organization && window.Echo) {
        window.Echo.private('organizations.' + organization)
            .listen('.network-device.status.changed', () => window.Livewire?.dispatch('dashboard-refresh'))
            .listen('.payment.status.changed', () => window.Livewire?.dispatch('dashboard-refresh'))
            .listen('.voucher.activated', () => window.Livewire?.dispatch('dashboard-refresh'))
            .listen('.session.updated', () => window.Livewire?.dispatch('dashboard-refresh'));
    }
});