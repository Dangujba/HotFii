<?php

namespace App\Livewire;

use App\Models\Organization;
use Livewire\Attributes\On;
use Livewire\Component;

class DashboardPulse extends Component
{
    public string $organizationUuid;

    public function mount(string $organizationUuid): void
    {
        $this->organizationUuid = $organizationUuid;
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

        return view('livewire.dashboard-pulse', ['stats' => [
            'revenue_today' => $organization->transactions()->whereDate('paid_at', today())->where('status', 'successful')->sum('gross_amount_kobo'),
            'active_sessions' => $organization->sessions()->where('status', 'active')->count(),
            'online_routers' => $organization->networkDevices()->where('status', 'online')->count(),
            'available_vouchers' => $organization->vouchers()->whereIn('status', ['generated', 'printed', 'assigned', 'sold'])->count(),
        ]]);
    }
}