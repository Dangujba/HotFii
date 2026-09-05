import { axisLabels, chrome, mount, naira, nairaCompact, readJson } from './charts.js';

const CHANNELS = [
    { key: 'online', label: 'Online' },
    { key: 'voucher', label: 'Vouchers' },
    { key: 'cash', label: 'Direct cash' },
];

function salesChart() {
    const element = document.querySelector('#hf-report-sales-chart');
    if (!element) return;

    const labels = readJson(element, 'labels');
    const values = readJson(element, 'series', {});

    mount(element, (t) => ({
        ...chrome(t),
        chart: { ...chrome(t).chart, type: 'bar', height: 320, stacked: true },
        series: CHANNELS.map((channel) => ({ name: channel.label, data: values[channel.key] || [] })),
        colors: t.series,
        plotOptions: { bar: { columnWidth: '58%', borderRadius: 3, borderRadiusApplication: 'end' } },
        dataLabels: { enabled: false },
        legend: { position: 'top', horizontalAlign: 'left', labels: { colors: t.muted }, markers: { size: 6 } },
        xaxis: {
            categories: labels,
            labels: axisLabels(t, { rotate: 0, hideOverlappingLabels: true }),
            axisBorder: { color: t.grid },
            axisTicks: { show: false },
            tooltip: { enabled: false },
        },
        yaxis: { labels: axisLabels(t, { formatter: nairaCompact }) },
        tooltip: { ...chrome(t).tooltip, y: { formatter: naira } },
        noData: { ...chrome(t).noData, text: 'No paid sales in this date range' },
    }));
}

function channelChart() {
    const element = document.querySelector('#hf-report-channel-chart');
    if (!element) return;

    const labels = readJson(element, 'labels');
    const values = readJson(element, 'values');
    const total = values.reduce((sum, value) => sum + Number(value), 0);

    mount(element, (t) => ({
        ...chrome(t),
        chart: { ...chrome(t).chart, type: 'donut', height: 240 },
        series: total > 0 ? values : [],
        labels: total > 0 ? labels : [],
        colors: t.series,
        stroke: { width: 2, colors: [t.surface] },
        legend: { show: false },
        dataLabels: { enabled: false },
        plotOptions: {
            pie: {
                expandOnClick: false,
                donut: {
                    size: '72%',
                    labels: {
                        show: total > 0,
                        name: { color: t.muted, fontSize: '11px' },
                        value: { color: t.ink, fontSize: '22px', fontWeight: 700, formatter: nairaCompact },
                        total: { show: true, label: 'Revenue', color: t.muted, formatter: () => nairaCompact(total) },
                    },
                },
            },
        },
        tooltip: { ...chrome(t).tooltip, y: { formatter: naira } },
        noData: { ...chrome(t).noData, text: 'No paid sales in this date range' },
    }));
}

function plansChart() {
    const element = document.querySelector('#hf-report-plans-chart');
    if (!element) return;

    const labels = readJson(element, 'labels');
    const values = readJson(element, 'values');

    mount(element, (t) => ({
        ...chrome(t),
        chart: { ...chrome(t).chart, type: 'bar', height: 320 },
        series: labels.length ? [{ name: 'Revenue', data: values }] : [],
        colors: [t.series[0]],
        plotOptions: { bar: { horizontal: true, barHeight: '54%', borderRadius: 4, borderRadiusApplication: 'end' } },
        dataLabels: {
            enabled: true,
            textAnchor: 'start',
            offsetX: 8,
            formatter: nairaCompact,
            style: { colors: [t.muted], fontSize: '11px', fontWeight: 600 },
        },
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
        noData: { ...chrome(t).noData, text: 'No paid plans in this date range' },
    }));
}

function dataLabel(megabytes) {
    const value = Number(megabytes || 0);
    return value >= 1024 ? `${(value / 1024).toFixed(1)} GB` : `${value.toFixed(value >= 10 ? 0 : 1)} MB`;
}

function usageChart() {
    const element = document.querySelector('#hf-report-usage-chart');
    if (!element) return;

    const labels = readJson(element, 'labels');
    const sessions = readJson(element, 'sessions');
    const megabytes = readJson(element, 'megabytes');

    mount(element, (t) => ({
        ...chrome(t),
        chart: { ...chrome(t).chart, type: 'line', height: 320 },
        series: [
            { name: 'Sessions', type: 'column', data: sessions },
            { name: 'Data', type: 'line', data: megabytes },
        ],
        colors: [t.series[1], t.series[2]],
        stroke: { width: [0, 3], curve: 'smooth', lineCap: 'round' },
        plotOptions: { bar: { columnWidth: '48%', borderRadius: 3, borderRadiusApplication: 'end' } },
        dataLabels: { enabled: false },
        markers: { size: [0, 3], strokeWidth: 2, strokeColors: t.surface, hover: { size: 5 } },
        legend: { position: 'top', horizontalAlign: 'left', labels: { colors: t.muted }, markers: { size: 6 } },
        xaxis: {
            categories: labels,
            labels: axisLabels(t, { rotate: 0, hideOverlappingLabels: true }),
            axisBorder: { color: t.grid },
            axisTicks: { show: false },
            tooltip: { enabled: false },
        },
        yaxis: [
            { title: { text: 'Sessions', style: { color: t.muted, fontSize: '11px' } }, labels: axisLabels(t, { formatter: (value) => String(Math.round(value)) }) },
            { opposite: true, title: { text: 'Data', style: { color: t.muted, fontSize: '11px' } }, labels: axisLabels(t, { formatter: dataLabel }) },
        ],
        tooltip: {
            ...chrome(t).tooltip,
            y: [
                { formatter: (value) => `${Math.round(value)} session${Math.round(value) === 1 ? '' : 's'}` },
                { formatter: dataLabel },
            ],
        },
        noData: { ...chrome(t).noData, text: 'No network usage in this date range' },
    }));
}

document.addEventListener('DOMContentLoaded', () => {
    salesChart();
    channelChart();
    plansChart();
    usageChart();
});
