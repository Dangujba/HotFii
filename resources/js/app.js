import './bootstrap';
import 'bootstrap';
import 'admin-lte';
import ApexCharts from 'apexcharts';
window.ApexCharts = ApexCharts;
import './echo';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-confirm]').forEach((element) => {
        element.addEventListener('click', (event) => {
            if (!window.confirm(element.dataset.confirm)) event.preventDefault();
        });
    });

    const organization = document.body.dataset.organizationUuid;
    if (organization && window.Echo) {
        window.Echo.private('organizations.' + organization)
            .listen('.network-device.status.changed', () => window.Livewire?.dispatch('dashboard-refresh'))
            .listen('.payment.status.changed', () => window.Livewire?.dispatch('dashboard-refresh'))
            .listen('.voucher.activated', () => window.Livewire?.dispatch('dashboard-refresh'))
            .listen('.session.updated', () => window.Livewire?.dispatch('dashboard-refresh'));
    }
});