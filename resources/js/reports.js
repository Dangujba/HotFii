import ApexCharts from 'apexcharts';

document.addEventListener('DOMContentLoaded', () => {
    const element = document.querySelector('#sales-chart');
    if (!element) return;

    const series = JSON.parse(element.dataset.series || '[]');
    const categories = JSON.parse(element.dataset.categories || '[]');

    const chart = new ApexCharts(element, {
        chart: {
            type: 'area',
            height: 320,
            toolbar: { show: false },
            background: 'transparent',
        },
        series: [{ name: 'Sales (₦)', data: series }],
        xaxis: { categories },
        colors: ['#f4610a'],
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.45,
                opacityTo: 0.05,
                stops: [20, 100, 100, 100],
            },
        },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2.5 },
        noData: { text: 'No paid sales in this date range' },
        theme: {
            mode: document.documentElement.getAttribute('data-bs-theme') || 'light',
        },
    });
    chart.render();

    window.addEventListener('theme-changed', (event) => {
        const isDark = event.detail.theme !== 'daylight';
        chart.updateOptions({
            theme: { mode: isDark ? 'dark' : 'light' },
        });
    });
});