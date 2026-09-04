import { axisLabels, chrome, mount, naira, nairaCompact, readJson } from './charts.js';

document.addEventListener('DOMContentLoaded', () => {
    const element = document.querySelector('#sales-chart');
    if (!element) return;

    const values = readJson(element, 'series');
    const categories = readJson(element, 'categories');
    const last = values.length - 1;

    mount(element, (t) => ({
        ...chrome(t),
        chart: { ...chrome(t).chart, type: 'area', height: 320 },
        series: [{ name: 'Sales', data: values }],
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
            categories,
            labels: axisLabels(t, { hideOverlappingLabels: true, rotate: 0 }),
            axisBorder: { color: t.grid },
            axisTicks: { show: false },
            crosshairs: { stroke: { color: t.grid, width: 1, dashArray: 0 } },
            tooltip: { enabled: false },
        },
        yaxis: { labels: axisLabels(t, { formatter: nairaCompact }) },
        tooltip: { ...chrome(t).tooltip, y: { formatter: naira } },
        noData: { ...chrome(t).noData, text: 'No paid sales in this date range' },
    }));
});
