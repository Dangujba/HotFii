import { axisLabels, chrome, mount, naira, nairaCompact, readJson } from './charts.js';

/**
 * Revenue over the trailing fortnight. One series, so no legend box — the card
 * heading already says what is plotted — and only the endpoint is labelled.
 */
function revenueChart() {
    const element = document.querySelector('#hf-revenue-chart');
    if (!element) return;

    const labels = readJson(element, 'labels');
    const values = readJson(element, 'values');
    const last = values.length - 1;

    mount(element, (t) => ({
        ...chrome(t),
        chart: { ...chrome(t).chart, type: 'area', height: 320 },
        series: [{ name: 'Revenue', data: values }],
        colors: [t.series[0]],
        stroke: { curve: 'smooth', width: 2, lineCap: 'round' },
        // A wash under the line, never a saturated block.
        fill: {
            type: 'gradient',
            gradient: { shadeIntensity: 1, opacityFrom: 0.18, opacityTo: 0.02, stops: [0, 100] },
        },
        dataLabels: {
            enabled: true,
            // The endpoint only. A number on every point is noise nobody reads.
            formatter: (value, { dataPointIndex }) => (dataPointIndex === last && value > 0 ? nairaCompact(value) : ''),
            offsetY: -10,
            background: { enabled: false },
            style: { colors: [t.muted], fontSize: '11px', fontWeight: 600 },
        },
        markers: {
            size: 0,
            strokeWidth: 2,
            strokeColors: t.surface,
            // End-dot carrying a 2px surface ring, so it reads where it meets the line.
            discrete: last >= 0
                ? [{ seriesIndex: 0, dataPointIndex: last, size: 5, fillColor: t.series[0], strokeColor: t.surface }]
                : [],
            hover: { size: 6 },
        },
        xaxis: {
            categories: labels,
            labels: axisLabels(t, { hideOverlappingLabels: true, rotate: 0 }),
            axisBorder: { color: t.grid },
            axisTicks: { show: false },
            crosshairs: { stroke: { color: t.grid, width: 1, dashArray: 0 } },
            tooltip: { enabled: false },
        },
        yaxis: { labels: axisLabels(t, { formatter: nairaCompact }) },
        tooltip: { ...chrome(t).tooltip, y: { formatter: naira } },
    }));
}

/**
 * Router fleet by state. Status colours are reserved and mean good..critical,
 * so the slice colour is bound to the state itself — a state dropping to zero
 * leaves the ring, it never repaints the states that remain.
 */
function fleetChart() {
    const element = document.querySelector('#hf-fleet-chart');
    if (!element) return;

    const slices = readJson(element, 'slices');
    if (!slices.length) return;

    const total = slices.reduce((sum, slice) => sum + slice.value, 0);

    mount(element, (t) => ({
        ...chrome(t),
        chart: { ...chrome(t).chart, type: 'donut', height: 248 },
        series: slices.map((slice) => slice.value),
        labels: slices.map((slice) => slice.label),
        colors: slices.map((slice) => t.status[slice.tone] ?? t.status.idle),
        // 2px of the card surface between slices. The gap separates them; a
        // stroke around each one would add ink that is not data.
        stroke: { width: 2, colors: [t.surface] },
        legend: { show: false },
        dataLabels: { enabled: false },
        plotOptions: {
            pie: {
                expandOnClick: false,
                donut: {
                    size: '74%',
                    labels: {
                        show: true,
                        // Apex lays the name/value pair out itself; nudging them by
                        // hand is how centre labels end up overlapping.
                        name: { fontSize: '11px', color: t.muted },
                        value: { fontSize: '26px', fontWeight: 700, color: t.ink },
                        total: {
                            show: true,
                            showAlways: true,
                            label: total === 1 ? 'router' : 'routers',
                            fontSize: '11px',
                            color: t.muted,
                            formatter: () => total,
                        },
                    },
                },
            },
        },
        tooltip: {
            ...chrome(t).tooltip,
            y: { formatter: (value) => `${value} router${value === 1 ? '' : 's'}` },
        },
    }));
}

/** Session starts by hour of the day. Ordered categories, one measure, one colour. */
function hourlyChart() {
    const element = document.querySelector('#hf-hourly-chart');
    if (!element) return;

    const labels = readJson(element, 'labels');
    const values = readJson(element, 'values');

    mount(element, (t) => ({
        ...chrome(t),
        chart: { ...chrome(t).chart, type: 'bar', height: 248 },
        series: [{ name: 'Session starts', data: values }],
        colors: [t.series[1]],
        // Thin columns with a rounded cap and a square foot on the baseline.
        plotOptions: { bar: { columnWidth: '50%', borderRadius: 4, borderRadiusApplication: 'end' } },
        // The busiest hour is called out in the card heading instead, so the
        // plot stays clean and no cap label risks being clipped at the top.
        dataLabels: { enabled: false },
        xaxis: {
            categories: labels,
            // 24 two-character ticks; Apex drops the ones that would collide,
            // which is steadier than picking every nth by hand.
            labels: axisLabels(t, { rotate: 0, rotateAlways: false, hideOverlappingLabels: true }),
            axisBorder: { color: t.grid },
            axisTicks: { show: false },
            tooltip: { enabled: false },
        },
        yaxis: { labels: axisLabels(t, { formatter: (value) => String(Math.round(value)) }) },
        tooltip: {
            ...chrome(t).tooltip,
            y: { formatter: (value) => `${value} session${value === 1 ? '' : 's'}` },
        },
    }));
}

/**
 * Revenue per plan. Nominal categories, so every bar takes slot 1 — colouring
 * them darker-where-bigger would spend the identity channel re-encoding the
 * bar length. The value rides the tip.
 */
function plansChart() {
    const element = document.querySelector('#hf-plans-chart');
    if (!element) return;

    const labels = readJson(element, 'labels');
    const values = readJson(element, 'values');
    if (!labels.length) return;

    mount(element, (t) => ({
        ...chrome(t),
        chart: { ...chrome(t).chart, type: 'bar', height: Math.max(232, labels.length * 46) },
        series: [{ name: 'Revenue', data: values }],
        colors: [t.series[0]],
        plotOptions: { bar: { horizontal: true, barHeight: '52%', borderRadius: 4, borderRadiusApplication: 'end' } },
        dataLabels: {
            enabled: true,
            // Outside the tip, so a short bar can never crop its own label.
            textAnchor: 'start',
            offsetX: 8,
            formatter: nairaCompact,
            style: { colors: [t.muted], fontSize: '11px', fontWeight: 600 },
        },
        // Room on the right for the tip labels; vertical rules for a bar chart.
        grid: {
            ...chrome(t).grid,
            padding: { top: 0, right: 64, bottom: 0, left: 4 },
            xaxis: { lines: { show: true } },
            yaxis: { lines: { show: false } },
        },
        xaxis: {
            categories: labels,
            labels: axisLabels(t, { formatter: nairaCompact }),
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: { labels: axisLabels(t, { maxWidth: 140 }) },
        tooltip: { ...chrome(t).tooltip, y: { formatter: naira } },
    }));
}

document.addEventListener('DOMContentLoaded', () => {
    revenueChart();
    fleetChart();
    hourlyChart();
    plansChart();
});
