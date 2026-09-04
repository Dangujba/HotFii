import { axisLabels, chrome, mount, naira, nairaCompact, readJson } from './charts.js';

/**
 * Charts for the platform console.
 *
 * Same conventions as the operator dashboard: slot 1 is money, slot 2 is a
 * count, status colours stay reserved for good..critical, and the plotted height
 * comes from the card's CSS class rather than a number repeated in two places.
 */
function height(element, fallback) {
    // Read once, before Apex replaces the element's own box with its canvas.
    return element.clientHeight || fallback;
}

/**
 * Gross volume across every tenant over the trailing fortnight. One series, so
 * no legend box — the card heading names it — and only the endpoint is labelled.
 */
function volumeChart() {
    const element = document.querySelector('#hf-volume-chart');
    if (!element) return;

    const labels = readJson(element, 'labels');
    const values = readJson(element, 'values');
    const last = values.length - 1;
    const box = height(element, 320);

    mount(element, (t) => ({
        ...chrome(t),
        chart: { ...chrome(t).chart, type: 'area', height: box },
        series: [{ name: 'Gross volume', data: values }],
        colors: [t.series[0]],
        stroke: { curve: 'smooth', width: 2, lineCap: 'round' },
        fill: {
            type: 'gradient',
            gradient: { shadeIntensity: 1, opacityFrom: 0.18, opacityTo: 0.02, stops: [0, 100] },
        },
        dataLabels: {
            enabled: true,
            formatter: (value, { dataPointIndex }) => (dataPointIndex === last && value > 0 ? nairaCompact(value) : ''),
            offsetY: -10,
            background: { enabled: false },
            style: { colors: [t.muted], fontSize: '11px', fontWeight: 600 },
        },
        markers: {
            size: 0,
            strokeWidth: 2,
            strokeColors: t.surface,
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
 * Organizations per account status. Nominal categories counting one measure, so
 * every bar takes slot 2 — the status palette is reserved for health, and a
 * lifecycle stage like "sandbox" is not a health state. The status name is on
 * the axis and the count is printed in the legend rows beside the plot, so
 * nothing here is readable by colour alone.
 */
function statusChart() {
    const element = document.querySelector('#hf-status-chart');
    if (!element) return;

    const labels = readJson(element, 'labels');
    const values = readJson(element, 'values');
    if (!labels.length) return;

    const box = Math.max(height(element, 240), labels.length * 34);

    mount(element, (t) => ({
        ...chrome(t),
        chart: { ...chrome(t).chart, type: 'bar', height: box },
        series: [{ name: 'Organizations', data: values }],
        colors: [t.series[1]],
        plotOptions: { bar: { horizontal: true, barHeight: '52%', borderRadius: 4, borderRadiusApplication: 'end' } },
        dataLabels: {
            enabled: true,
            // Outside the tip, so a one-organization bar cannot crop its own label.
            textAnchor: 'start',
            offsetX: 8,
            formatter: (value) => (value > 0 ? String(Math.round(value)) : ''),
            style: { colors: [t.muted], fontSize: '11px', fontWeight: 600 },
        },
        grid: {
            ...chrome(t).grid,
            padding: { top: 0, right: 40, bottom: 0, left: 4 },
            xaxis: { lines: { show: true } },
            yaxis: { lines: { show: false } },
        },
        xaxis: {
            categories: labels,
            labels: axisLabels(t, { formatter: (value) => String(Math.round(value)) }),
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: { labels: axisLabels(t, { maxWidth: 120 }) },
        tooltip: {
            ...chrome(t).tooltip,
            y: { formatter: (value) => `${value} organization${value === 1 ? '' : 's'}` },
        },
    }));
}

/**
 * Fees earned against fees collected at the gateway. Two measures in the same
 * unit, so one axis and the validated two-hue pair, with a legend present
 * because identity can never rest on colour alone.
 */
function feesChart() {
    const element = document.querySelector('#hf-fees-chart');
    if (!element) return;

    const labels = readJson(element, 'labels');
    const accrued = readJson(element, 'accrued');
    const collected = readJson(element, 'collected');
    if (!labels.length) return;

    const box = height(element, 240);

    mount(element, (t) => ({
        ...chrome(t),
        chart: { ...chrome(t).chart, type: 'bar', height: box },
        series: [
            { name: 'Earned', data: accrued },
            { name: 'Collected at gateway', data: collected },
        ],
        colors: [t.series[0], t.series[1]],
        // Thin grouped columns, 2px of card surface between the pair.
        plotOptions: { bar: { columnWidth: '56%', borderRadius: 4, borderRadiusApplication: 'end' } },
        stroke: { show: true, width: 2, colors: ['transparent'] },
        // The month table below the card prints every figure, so the plot stays clean.
        dataLabels: { enabled: false },
        legend: {
            show: true,
            position: 'top',
            horizontalAlign: 'left',
            markers: { size: 8, strokeWidth: 0 },
            labels: { colors: t.muted },
            fontSize: '12px',
            fontWeight: 500,
            itemMargin: { horizontal: 10 },
        },
        xaxis: {
            categories: labels,
            labels: axisLabels(t, { rotate: 0, hideOverlappingLabels: true }),
            axisBorder: { color: t.grid },
            axisTicks: { show: false },
            tooltip: { enabled: false },
        },
        yaxis: { labels: axisLabels(t, { formatter: nairaCompact }) },
        tooltip: { ...chrome(t).tooltip, shared: true, intersect: false, y: { formatter: naira } },
    }));
}

document.addEventListener('DOMContentLoaded', () => {
    volumeChart();
    statusChart();
    feesChart();
});
