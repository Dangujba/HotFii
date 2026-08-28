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
   HotFii Dialogs (swal-style, zero dependency)
   HotFiiSwal.confirm({...}) -> Promise<boolean>
   HotFiiSwal.toast('Copied')
   ─────────────────────────────────────────────── */
const HotFiiSwal = (() => {
    const ICONS = {
        warning: 'bi-exclamation-triangle-fill',
        danger:  'bi-exclamation-octagon-fill',
        info:    'bi-info-circle-fill',
        success: 'bi-check-circle-fill',
        question:'bi-patch-question-fill',
    };

    let open = null;

    function close(result) {
        if (!open) return;
        const { overlay, resolve, restoreFocus } = open;
        open = null;
        overlay.classList.remove('is-open');
        document.body.classList.remove('hf-swal-lock');
        setTimeout(() => overlay.remove(), 180);
        if (restoreFocus && document.contains(restoreFocus)) restoreFocus.focus();
        resolve(result);
    }

    function fire(options = {}) {
        // Only one dialog at a time — a second call cancels the first.
        if (open) close(false);

        const {
            title = 'Are you sure?',
            text = '',
            icon = 'warning',
            confirmText = 'Yes, continue',
            cancelText = 'Cancel',
            showCancel = true,
            confirmVariant = icon === 'danger' ? 'danger' : 'primary',
        } = options;

        const overlay = document.createElement('div');
        overlay.className = 'hf-swal-overlay';
        overlay.innerHTML = `
            <div class="hf-swal" role="dialog" aria-modal="true" aria-labelledby="hf-swal-title">
                <div class="hf-swal-icon hf-swal-icon-${icon}"><i class="bi ${ICONS[icon] || ICONS.warning}"></i></div>
                <h2 class="hf-swal-title" id="hf-swal-title"></h2>
                <p class="hf-swal-text"></p>
                <div class="hf-swal-actions">
                    ${showCancel ? `<button type="button" class="hf-swal-btn hf-swal-btn-cancel" data-hf-swal="cancel"></button>` : ''}
                    <button type="button" class="hf-swal-btn hf-swal-btn-${confirmVariant}" data-hf-swal="confirm"></button>
                </div>
            </div>`;

        // textContent, never innerHTML — dialog copy can carry customer/org names.
        overlay.querySelector('.hf-swal-title').textContent = title;
        const textEl = overlay.querySelector('.hf-swal-text');
        if (text) { textEl.textContent = text; } else { textEl.remove(); }
        overlay.querySelector('[data-hf-swal="confirm"]').textContent = confirmText;
        if (showCancel) overlay.querySelector('[data-hf-swal="cancel"]').textContent = cancelText;

        document.body.appendChild(overlay);
        document.body.classList.add('hf-swal-lock');
        requestAnimationFrame(() => overlay.classList.add('is-open'));

        const promise = new Promise((resolve) => {
            open = { overlay, resolve, restoreFocus: document.activeElement };
        });

        overlay.addEventListener('click', (event) => {
            const action = event.target.closest('[data-hf-swal]')?.dataset.hfSwal;
            if (action) return close(action === 'confirm');
            if (event.target === overlay) close(false);
        });

        // Keep focus inside the dialog.
        overlay.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') return close(false);
            if (event.key !== 'Tab') return;
            const focusable = overlay.querySelectorAll('button');
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });

        overlay.querySelector('[data-hf-swal="confirm"]').focus();

        return promise;
    }

    function confirm(options) {
        return fire(options);
    }

    function toast(message, icon = 'success') {
        const el = document.createElement('div');
        el.className = `hf-toast hf-toast-${icon}`;
        el.setAttribute('role', 'status');
        const i = document.createElement('i');
        i.className = `bi ${ICONS[icon] || ICONS.success} me-2`;
        el.appendChild(i);
        el.appendChild(document.createTextNode(message));
        document.body.appendChild(el);
        requestAnimationFrame(() => el.classList.add('is-open'));
        setTimeout(() => {
            el.classList.remove('is-open');
            setTimeout(() => el.remove(), 250);
        }, 2200);
    }

    return { fire, confirm, toast };
})();

window.HotFiiSwal = HotFiiSwal;

/* ───────────────────────────────────────────────
   Clipboard — works on plain http too, where
   navigator.clipboard is unavailable.
   ─────────────────────────────────────────────── */
async function hotFiiCopy(value) {
    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(value);
            return true;
        } catch { /* fall through to the textarea path */ }
    }
    const ta = document.createElement('textarea');
    ta.value = value;
    ta.setAttribute('readonly', '');
    ta.style.cssText = 'position:fixed;top:0;left:-9999px;opacity:0;';
    document.body.appendChild(ta);
    ta.select();
    ta.setSelectionRange(0, ta.value.length);
    let ok = false;
    try { ok = document.execCommand('copy'); } catch { ok = false; }
    ta.remove();
    return ok;
}

window.hotFiiCopy = hotFiiCopy;

/* ───────────────────────────────────────────────
   HotFii Custom Select
   Progressive enhancement over a native <select>:
   the real element stays in the DOM at opacity 0 so
   HTML5 required-validation can still focus it.
   ─────────────────────────────────────────────── */
const HotFiiSelect = (() => {
    // Long lists get a search box; short ones do not need one.
    const SEARCH_THRESHOLD = 8;
    let counter = 0;

    function build(select) {
        if (select.dataset.hfSelect === 'ready' || select.multiple || select.hidden) return;
        if (select.closest('.hf-select')) return;
        select.dataset.hfSelect = 'ready';

        const id = `hf-select-${++counter}`;
        const wrap = document.createElement('div');
        wrap.className = 'hf-select';
        if (select.classList.contains('form-select-sm')) wrap.classList.add('hf-select-sm');

        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(select);

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'hf-select-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.id = `${id}-trigger`;
        if (select.disabled) trigger.disabled = true;
        trigger.innerHTML = '<span class="hf-select-value"></span><i class="bi bi-chevron-down hf-select-caret"></i>';

        const panel = document.createElement('div');
        panel.className = 'hf-select-panel';
        panel.innerHTML = `
            <div class="hf-select-search"><i class="bi bi-search"></i><input type="text" autocomplete="off" spellcheck="false"></div>
            <div class="hf-select-list" role="listbox" id="${id}-list" tabindex="-1"></div>
            <div class="hf-select-empty">No match</div>`;

        const search = panel.querySelector('.hf-select-search');
        const searchInput = panel.querySelector('.hf-select-search input');
        const list = panel.querySelector('.hf-select-list');
        const empty = panel.querySelector('.hf-select-empty');

        searchInput.placeholder = select.dataset.searchPlaceholder || 'Search';
        trigger.setAttribute('aria-controls', `${id}-list`);

        wrap.append(trigger, panel);

        // Reuse the select's label for the trigger, so clicking the label
        // still opens the custom control.
        const label = select.id ? document.querySelector(`label[for="${select.id}"]`) : null;
        if (label) {
            label.setAttribute('for', trigger.id);
            trigger.setAttribute('aria-labelledby', label.id || (label.id = `${id}-label`));
        }

        function renderOptions() {
            list.innerHTML = '';
            Array.from(select.options).forEach((option, index) => {
                const item = document.createElement('div');
                item.className = 'hf-select-option';
                item.setAttribute('role', 'option');
                item.dataset.index = String(index);
                item.textContent = option.textContent.trim();
                if (option.disabled) item.classList.add('is-disabled');
                if (option.value === '') item.classList.add('is-placeholder');
                if (index === select.selectedIndex) {
                    item.classList.add('is-selected');
                    item.setAttribute('aria-selected', 'true');
                }
                list.appendChild(item);
            });
            search.style.display = select.options.length >= SEARCH_THRESHOLD ? '' : 'none';
        }

        function syncTrigger() {
            const option = select.options[select.selectedIndex];
            const value = option ? option.textContent.trim() : '';
            const valueEl = trigger.querySelector('.hf-select-value');
            valueEl.textContent = value;
            valueEl.classList.toggle('is-placeholder', !option || option.value === '');
        }

        function filter(term) {
            const needle = term.trim().toLowerCase();
            let visible = 0;
            list.querySelectorAll('.hf-select-option').forEach((item) => {
                const match = !needle || item.textContent.toLowerCase().includes(needle);
                item.style.display = match ? '' : 'none';
                if (match) visible += 1;
            });
            empty.style.display = visible === 0 ? '' : 'none';
        }

        function activeItems() {
            return Array.from(list.querySelectorAll('.hf-select-option'))
                .filter((item) => item.style.display !== 'none' && !item.classList.contains('is-disabled'));
        }

        function highlight(item) {
            list.querySelectorAll('.hf-select-option.is-active').forEach((el) => el.classList.remove('is-active'));
            if (!item) return;
            item.classList.add('is-active');
            item.scrollIntoView({ block: 'nearest' });
        }

        function open() {
            if (wrap.classList.contains('is-open')) return;
            document.querySelectorAll('.hf-select.is-open').forEach((other) => other.hfSelectClose?.());
            renderOptions();
            filter('');
            wrap.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');

            // Flip upward when there is not enough room below.
            const room = window.innerHeight - trigger.getBoundingClientRect().bottom;
            wrap.classList.toggle('is-flipped', room < 260);

            searchInput.value = '';
            highlight(list.querySelector('.hf-select-option.is-selected') || activeItems()[0]);
            if (search.style.display !== 'none') searchInput.focus();
        }

        function close(refocus = false) {
            if (!wrap.classList.contains('is-open')) return;
            wrap.classList.remove('is-open', 'is-flipped');
            trigger.setAttribute('aria-expanded', 'false');
            if (refocus) trigger.focus();
        }

        wrap.hfSelectClose = close;

        function choose(item) {
            if (!item || item.classList.contains('is-disabled')) return;
            select.selectedIndex = Number(item.dataset.index);
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
            syncTrigger();
            close(true);
        }

        trigger.addEventListener('click', () => (wrap.classList.contains('is-open') ? close() : open()));

        list.addEventListener('click', (event) => choose(event.target.closest('.hf-select-option')));
        list.addEventListener('mousemove', (event) => {
            const item = event.target.closest('.hf-select-option');
            if (item && !item.classList.contains('is-disabled')) highlight(item);
        });

        searchInput.addEventListener('input', () => {
            filter(searchInput.value);
            highlight(activeItems()[0]);
        });

        wrap.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.stopPropagation();
                return close(true);
            }

            if (!wrap.classList.contains('is-open')) {
                if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
                    event.preventDefault();
                    open();
                }
                return;
            }

            const items = activeItems();
            const current = items.indexOf(list.querySelector('.hf-select-option.is-active'));

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                highlight(items[Math.min(current + 1, items.length - 1)] || items[0]);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                highlight(items[Math.max(current - 1, 0)] || items[0]);
            } else if (event.key === 'Enter') {
                event.preventDefault();
                choose(list.querySelector('.hf-select-option.is-active'));
            } else if (event.key === 'Tab') {
                close();
            }
        });

        // Native validation focuses the hidden select; show the dropdown instead.
        select.addEventListener('focus', () => trigger.focus());
        select.addEventListener('invalid', () => wrap.classList.add('is-invalid'));
        select.addEventListener('change', syncTrigger);
        trigger.addEventListener('focus', () => wrap.classList.remove('is-invalid'));

        renderOptions();
        syncTrigger();
    }

    function init(root = document) {
        root.querySelectorAll('select.form-select:not([data-hf-select])').forEach(build);
    }

    document.addEventListener('click', (event) => {
        if (event.target.closest('.hf-select')) return;
        document.querySelectorAll('.hf-select.is-open').forEach((wrap) => wrap.hfSelectClose?.());
    });

    return { init, build };
})();

window.HotFiiSelect = HotFiiSelect;

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
    // 0. Upgrade every native select to the searchable custom control
    HotFiiSelect.init();

    // 1. Theme toggle button
    const toggle = document.getElementById('theme-toggle');
    if (toggle) {
        toggle.addEventListener('click', () => ThemeSwitcher.cycle());
    }

    // 2. Confirm dialog handler — swal-style, delegated so it covers
    //    Livewire-rendered markup too.
    document.addEventListener('click', (event) => {
        const element = event.target.closest('[data-confirm]');
        if (!element) return;

        // Second pass after the user confirmed — let it through untouched.
        if (element.dataset.hfConfirmed === '1') {
            delete element.dataset.hfConfirmed;
            return;
        }

        const form = element.form || element.closest('form');
        const submits = form
            && (element.tagName === 'BUTTON' || element.tagName === 'INPUT')
            && element.type !== 'button'
            && element.type !== 'reset';

        // Don't ask about a form the browser is going to reject anyway.
        if (form && form.checkValidity && !form.checkValidity()) return;

        event.preventDefault();
        event.stopPropagation();

        HotFiiSwal.confirm({
            title: element.dataset.confirmTitle || 'Are you sure?',
            text: element.dataset.confirm,
            icon: element.dataset.confirmIcon || 'warning',
            confirmText: element.dataset.confirmButton || 'Yes, continue',
        }).then((confirmed) => {
            if (!confirmed) return;

            element.dataset.hfConfirmed = '1';

            if (submits) {
                // requestSubmit fires 'submit', so handler 3 attaches the spinner.
                form.requestSubmit(element.tagName === 'BUTTON' ? element : undefined);
                return;
            }

            HotFiiLoader.start();
            HotFiiLoader.attachButtonSpinner(element);

            if (element.tagName === 'A' && element.href) {
                window.location.href = element.href;
            } else {
                element.click();
            }
        });
    }, true);

    // 2b. Copy to clipboard: data-copy-target="#id" or data-copy-text="literal"
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-copy-target], [data-copy-text]');
        if (!button) return;

        event.preventDefault();

        let value = button.dataset.copyText;
        if (value === undefined) {
            const source = document.querySelector(button.dataset.copyTarget);
            if (!source) return;
            value = 'value' in source && source.value !== undefined ? source.value : source.textContent;
        }

        const copied = await hotFiiCopy(value);

        if (!copied) {
            HotFiiSwal.toast('Press Ctrl+C to copy', 'warning');
            return;
        }

        HotFiiSwal.toast(button.dataset.copyMessage || 'Copied to clipboard');

        // Momentary "copied" state on the button itself.
        const icon = button.querySelector('i');
        const label = button.querySelector('[data-copy-label]');
        const previousIcon = icon?.className;
        const previousLabel = label?.textContent;
        if (icon) icon.className = 'bi bi-check2 me-1';
        if (label) label.textContent = 'Copied';
        button.classList.add('is-copied');
        setTimeout(() => {
            if (icon && previousIcon) icon.className = previousIcon;
            if (label && previousLabel) label.textContent = previousLabel;
            button.classList.remove('is-copied');
        }, 1800);
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

            // Re-upgrade selects Livewire re-rendered.
            window.Livewire.hook('morphed', () => HotFiiSelect.init());
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