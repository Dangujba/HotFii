<?php

namespace App\Livewire;

use App\Models\Organization;
use App\Services\Dashboard\OrganizationDashboardService;
use Livewire\Attributes\On;
use Livewire\Component;

class DashboardPulse extends Component
{
    public string $organizationUuid;

    public ?int $networkDeviceId = null;

    public function mount(string $organizationUuid, ?int $networkDeviceId = null): void
    {
        $this->organizationUuid = $organizationUuid;
        $this->networkDeviceId = $networkDeviceId;
    }

    #[On('dashboard-refresh')]
    public function refreshMetrics(): void
    {
        // Rendering again performs fresh aggregate queries.
    }

    public function render()
    {
        $organization = auth()->user()->is_platform_admin && session()->has('impersonated_organization_id')
            ? Organization::where('uuid', $this->organizationUuid)->firstOrFail()
            : auth()->user()->organizations()->where('uuid', $this->organizationUuid)->firstOrFail();

        $pulse = app(OrganizationDashboardService::class)->pulse($organization, $this->networkDeviceId);

        return view('livewire.dashboard-pulse', [
            'tiles' => [
                [
                    'label' => 'Revenue today',
                    'value' => '₦'.number_format($pulse['revenue_today_kobo'] / 100, 0),
                    'icon' => 'cash-stack',
                    'tone' => 'money',
                    'delta' => $pulse['revenue_delta'],
                    'foot' => 'vs ₦'.number_format($pulse['revenue_yesterday_kobo'] / 100, 0).' yesterday',
                ],
                [
                    'label' => 'Sales today',
                    'value' => number_format($pulse['sales_today']),
                    'icon' => 'bag-check',
                    'tone' => 'money',
                    'delta' => $pulse['sales_delta'],
                    'foot' => number_format($pulse['sales_yesterday']).' yesterday',
                ],
                [
                    'label' => 'Active sessions',
                    'value' => number_format($pulse['active_sessions']),
                    'icon' => 'broadcast',
                    'tone' => 'usage',
                    'delta' => $pulse['sessions_delta'],
                    'foot' => number_format($pulse['sessions_started_today']).' started today',
                ],
                [
                    'label' => 'Online routers',
                    'value' => number_format($pulse['online_routers']),
                    'icon' => 'router',
                    'tone' => 'usage',
                    'delta' => null,
                    'foot' => 'of '.number_format($pulse['total_routers']).' in the fleet',
                ],
                [
                    'label' => 'Available vouchers',
                    'value' => number_format($pulse['available_vouchers']),
                    'icon' => 'ticket-perforated',
                    'tone' => 'money',
                    'delta' => null,
                    'foot' => number_format($pulse['vouchers_in_use']).' in use',
                ],
            ],
        ]);
    }
}
