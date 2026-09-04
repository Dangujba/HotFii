import ApexCharts from 'apexcharts';

/**
 * Shared chart chrome.
 *
 * Every colour is read from a CSS custom property rather than written here.
 * HotFii has three themes and a chart mark is judged against the card it sits
 * on, not against the page, so each theme supplies its own validated step and
 * a chart only ever asks for the token by name.
 */
export function tokens() {
    const style = getComputedStyle(document.documentElement);
    const read = (name, fallback) => style.getPropertyValue(name).trim() || fallback;
    const theme = document.documentElement.getAttribute('data-hotfii-theme') || 'daylight';

    return {
        dark: theme !== 'daylight',
        surface: read('--hf-surface-card', '#ffffff'),
        ink: read('--hf-ink', '#1e1a17'),
        muted: read('--hf-ink-secondary', '#6b5f56'),
        grid: read('--hf-chart-grid', '#ede5dc'),
        // Slot 1 is money, slot 2 is usage. Assigned in this order, never cycled.
        series: [read('--hf-chart-1', '#f4610a'), read('--hf-chart-2', '#0f9b76')],
        // Reserved: these mean good..critical and are never used for "series 3".
        status: {
            good: read('--hf-status-good', '#0ca30c'),
            warning: read('--hf-status-warning', '#fab219'),
            serious: read('--hf-status-serious', '#ec835a'),
            critical: read('--hf-status-critical', '#d03b3b'),
            idle: read('--hf-status-idle', '#8b8178'),
        },
    };
}

/**
 * Chrome shared by every chart: no toolbar, transparent card, and a grid that
 * recedes — solid hairlines one step off the surface, never dashed, horizontal
 * only. The data is the only thing allowed to be loud.
 */
export function chrome(t) {
    return {
        chart: {
            toolbar: { show: false },
            background: 'transparent',
            fontFamily: 'inherit',
            animations: { enabled: true, speed: 400 },
        },
        grid: {
            borderColor: t.grid,
            strokeDashArray: 0,
            xaxis: { lines: { show: false } },
            yaxis: { lines: { show: true } },
            padding: { top: 4, right: 12, bottom: 0, left: 12 },
        },
        states: { hover: { filter: { type: 'lighten', value: 0.08 } } },
        tooltip: { theme: t.dark ? 'dark' : 'light' },
        theme: { mode: t.dark ? 'dark' : 'light' },
        noData: { text: 'Nothing recorded yet', style: { color: t.muted, fontSize: '13px' } },
    };
}

/** Axis text wears muted ink, never the series colour. */
export function axisLabels(t, extra = {}) {
    return {
        style: { colors: t.muted, fontSize: '11px', fontWeight: 500 },
        ...extra,
    };
}

/**
 * Render, then rebuild from fresh tokens on every theme change. The whole
 * option set is recomputed rather than patched, so no colour can be left
 * behind on the theme it was born in.
 */
export function mount(element, build) {
    const chart = new ApexCharts(element, build(tokens()));
    chart.render();

    window.addEventListener('theme-changed', () => chart.updateOptions(build(tokens()), false, false));

    return chart;
}

/** Chart data travels in data-* attributes; a malformed one must not kill the page. */
export function readJson(element, key, fallback = []) {
    try {
        return JSON.parse(element.dataset[key] || '');
    } catch {
        return fallback;
    }
}

export function naira(value) {
    return '₦' + Number(value || 0).toLocaleString('en-NG', { maximumFractionDigits: 2 });
}

/** Short enough for an axis tick: ₦4.2k, ₦1.8M. */
export function nairaCompact(value) {
    const amount = Number(value || 0);

    if (amount >= 1000000) {
        return '₦' + (amount / 1000000).toFixed(amount >= 10000000 ? 0 : 1) + 'M';
    }

    if (amount >= 1000) {
        return '₦' + (amount / 1000).toFixed(amount >= 10000 ? 0 : 1) + 'k';
    }

    return '₦' + amount.toFixed(0);
}
