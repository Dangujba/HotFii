import './bootstrap';
import 'bootstrap';
import 'admin-lte';
import ApexCharts from 'apexcharts';
window.ApexCharts = ApexCharts;
import './echo';

/* ───────────────────────────────────────────────
   HotFii Global Loader / Spinner Engine
   Handles:
   1. Top Progress Bar for page transitions & AJAX
   2. Automated button spinners on every form submit & action click
   3. Livewire 3 request lifecycle hooks
   ─────────────────────────────────────────────── */
const HotFiiLoader = (() => {
    let bar = null;
    let timer = null;
    let progress = 0;

    function getBar() {
        if (!bar) {
            bar = document.getElementById('hotfii-loader-bar');
            if (!bar) {
                bar = document.createElement('div');
                bar.id = 'hotfii-loader-bar';
                document.body.appendChild(bar);
            }
        }
        return bar;
    }

    function set(p) {
        progress = Math.min(Math.max(p, 0), 100);
        const b = getBar();
        b.style.width = progress + '%';
        if (progress > 0 && progress < 100) {
            b.classList.add('active');
        }
    }

    function start() {
        if (timer) clearInterval(timer);
        set(15);
        timer = setInterval(() => {
            if (progress < 85) {
                set(progress + Math.random() * 12 + 2);
            }
        }, 150);
    }

    function done() {
        if (timer) clearInterval(timer);
        set(100);
        setTimeout(() => {
            const b = getBar();
            b.classList.remove('active');
            setTimeout(() => {
                b.style.width = '0%';
                progress = 0;
            }, 300);
        }, 200);
    }

    function attachButtonSpinner(button) {
        if (!button || button.classList.contains('is-loading')) return;
        button.classList.add('is-loading');
        button.setAttribute('disabled', 'disabled');

        // Check if button already contains a spinner
        if (!button.querySelector('.spinner-border')) {
            const spinner = document.createElement('span');
            spinner.className = 'spinner-border spinner-border-sm me-2 align-middle';
            spinner.setAttribute('role', 'status');
            spinner.setAttribute('aria-hidden', 'true');

            // If button has an icon as first child, replace or prepend
            const icon = button.querySelector('i');
            if (icon && icon === button.firstElementChild) {
                icon.style.display = 'none';
            }
            button.prepend(spinner);
        }
    }

    function removeButtonSpinner(button) {
        if (!button) return;
        button.classList.remove('is-loading');
        button.removeAttribute('disabled');
        const spinner = button.querySelector('.spinner-border');
        if (spinner) spinner.remove();
        const icon = button.querySelector('i');
        if (icon) icon.style.display = '';
    }

    return { start, done, set, attachButtonSpinner, removeButtonSpinner };
})();

window.HotFiiLoader = HotFiiLoader;

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
    const BS_MAP = {
        daylight: 'light',
        dusk:     'dark',
        midnight: 'dark',
    };

    function detect() {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved && THEMES.includes(saved)) return saved;
        if (window.matchMedia?.('(prefers-color-scheme: dark)').matches) return 'midnight';
        return 'daylight';
    }

    function apply(theme) {
        const html = document.documentElement;
        html.setAttribute('data-hotfii-theme', theme);
        html.setAttribute('data-bs-theme', BS_MAP[theme]);
        localStorage.setItem(STORAGE_KEY, theme);

        const btn = document.getElementById('theme-toggle');
        if (btn) {
            const icon = btn.querySelector('i');
            if (icon) {
                icon.classList.remove(...Object.values(ICONS));
                icon.classList.add(ICONS[theme]);
            }
            btn.title = `Theme: ${theme.charAt(0).toUpperCase() + theme.slice(1)}`;
        }

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
        window.matchMedia?.('(prefers-color-scheme: dark)')
            .addEventListener('change', (e) => {
                if (!localStorage.getItem(STORAGE_KEY)) {
                    apply(e.matches ? 'midnight' : 'daylight');
                }
            });
    }

    return { init, apply, cycle, detect, THEMES };
})();

ThemeSwitcher.init();
window.HotFiiTheme = ThemeSwitcher;

document.addEventListener('DOMContentLoaded', () => {
    // 1. Theme toggle button
    const toggle = document.getElementById('theme-toggle');
    if (toggle) {
        toggle.addEventListener('click', () => ThemeSwitcher.cycle());
    }

    // 2. Confirm dialog handler
    document.querySelectorAll('[data-confirm]').forEach((element) => {
        element.addEventListener('click', (event) => {
            if (!window.confirm(element.dataset.confirm)) {
                event.preventDefault();
            } else {
                HotFiiLoader.start();
                HotFiiLoader.attachButtonSpinner(element);
            }
        });
    });

    // 3. Automatic spinner on form submit
    document.addEventListener('submit', (event) => {
        const form = event.target;
        // Don't disable if form has novalidate and is invalid
        if (form.checkValidity && !form.checkValidity()) {
            return;
        }
        const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]') || document.activeElement;
        if (submitBtn && (submitBtn.tagName === 'BUTTON' || submitBtn.tagName === 'INPUT')) {
            HotFiiLoader.attachButtonSpinner(submitBtn);
        }
        HotFiiLoader.start();
    });

    // 4. Page navigation loading feedback
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a');
        if (!link) return;

        const href = link.getAttribute('href');
        const target = link.getAttribute('target');

        // Only handle valid internal navigation links
        if (
            href &&
            !href.startsWith('#') &&
            !href.startsWith('javascript:') &&
            !href.startsWith('mailto:') &&
            !href.startsWith('tel:') &&
            (!target || target === '_self') &&
            !link.hasAttribute('download') &&
            !event.ctrlKey &&
            !event.metaKey &&
            !event.shiftKey
        ) {
            HotFiiLoader.start();
        }
    });

    // Stop loader when navigating back or page fully shown
    window.addEventListener('pageshow', () => {
        HotFiiLoader.done();
    });

    // 5. Livewire 3 Request / Commit Hooks
    document.addEventListener('livewire:init', () => {
        if (window.Livewire) {
            window.Livewire.hook('request', ({ respond, succeed, fail }) => {
                HotFiiLoader.start();
                respond(() => HotFiiLoader.done());
                succeed(() => HotFiiLoader.done());
                fail(() => HotFiiLoader.done());
            });
        }
    });

    // 6. Livewire real-time channel listeners
    const organization = document.body.dataset.organizationUuid;
    if (organization && window.Echo) {
        window.Echo.private('organizations.' + organization)
            .listen('.network-device.status.changed', () => window.Livewire?.dispatch('dashboard-refresh'))
            .listen('.payment.status.changed', () => window.Livewire?.dispatch('dashboard-refresh'))
            .listen('.voucher.activated', () => window.Livewire?.dispatch('dashboard-refresh'))
            .listen('.session.updated', () => window.Livewire?.dispatch('dashboard-refresh'));
    }
});