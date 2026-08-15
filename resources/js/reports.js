import ApexCharts from 'apexcharts';

document.addEventListener('DOMContentLoaded', () => {
    const element = document.querySelector('#sales-chart');
    if (!element) return;

    const series = JSON.parse(element.dataset.series || '[]');
    const categories = JSON.parse(element.dataset.categories || '[]');

    new ApexCharts(element, {
        chart: { type: 'area', height: 320, toolbar: { show: false } },
        series: [{ name: 'Sales (₦)', data: series }],
        xaxis: { categories },
        colors: ['#146c43'],
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth' },
        noData: { text: 'No paid sales in this date range' },
    }).render();
});