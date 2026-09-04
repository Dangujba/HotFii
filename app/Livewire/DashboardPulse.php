<?php

namespace App\Livewire;

use App\Models\Organization;
use Carbon\CarbonImmutable;
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

        $today = CarbonImmutable::today();
        $yesterday = $today->subDay();
        // Both days come out of one scan, and the scan is bounded by an indexed
        // range rather than a CASE over the whole table.
        $window = [$yesterday->startOfDay(), $today->endOfDay()];
        $days = [$today->toDateString(), $yesterday->toDateString()];

        $money = $organization->transactions()
            ->where('status', 'successful')
            ->whereBetween('paid_at', $window)
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN DATE(paid_at) = ? THEN gross_amount_kobo ELSE 0 END), 0) as today_kobo,
                 COALESCE(SUM(CASE WHEN DATE(paid_at) = ? THEN gross_amount_kobo ELSE 0 END), 0) as prior_kobo,
                 COALESCE(SUM(CASE WHEN DATE(paid_at) = ? THEN 1 ELSE 0 END), 0) as today_sales,
                 COALESCE(SUM(CASE WHEN DATE(paid_at) = ? THEN 1 ELSE 0 END), 0) as prior_sales',
                [...$days, ...$days]
            )
            ->first();

        // Active sessions stay their own query: (organization, status) is indexed,
        // and folding it into the windowed scan above would cost a full table pass.
        $active = $organization->sessions()->where('status', 'active')->count();

        $starts = $organization->sessions()
            ->whereBetween('started_at', $window)
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN DATE(started_at) = ? THEN 1 ELSE 0 END), 0) as today_starts,
                 COALESCE(SUM(CASE WHEN DATE(started_at) = ? THEN 1 ELSE 0 END), 0) as prior_starts',
                $days
            )
            ->first();

        $devices = $organization->networkDevices()
            ->selectRaw("COUNT(*) as fleet, COALESCE(SUM(CASE WHEN status = 'online' THEN 1 ELSE 0 END), 0) as online")
            ->first();

        // "Available" keeps its original meaning: still redeemable. A sold voucher
        // has not been used yet, so it belongs here; an active one does not.
        $vouchers = $organization->vouchers()
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN status IN ('generated', 'printed', 'assigned', 'sold') THEN 1 ELSE 0 END), 0) as available,
                 COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) as in_use"
            )
            ->first();

        $revenueToday = (int) $money->today_kobo;
        $todayStarts = (int) $starts->today_starts;

        return view('livewire.dashboard-pulse', [
            'tiles' => [
                [
                    'label' => 'Revenue today',
                    'value' => '₦'.number_format($revenueToday / 100, 0),
                    'icon' => 'cash-stack',
                    'tone' => 'money',
                    'delta' => $this->delta($revenueToday, (int) $money->prior_kobo),
                    'foot' => 'vs ₦'.number_format(((int) $money->prior_kobo) / 100, 0).' yesterday',
                ],
                [
                    'label' => 'Sales today',
                    'value' => number_format((int) $money->today_sales),
                    'icon' => 'bag-check',
                    'tone' => 'money',
                    'delta' => $this->delta((int) $money->today_sales, (int) $money->prior_sales),
                    'foot' => number_format((int) $money->prior_sales).' yesterday',
                ],
                [
                    'label' => 'Active sessions',
                    'value' => number_format($active),
                    'icon' => 'broadcast',
                    'tone' => 'usage',
                    'delta' => $this->delta($todayStarts, (int) $starts->prior_starts),
                    'foot' => number_format($todayStarts).' started today',
                ],
                [
                    'label' => 'Online routers',
                    'value' => number_format((int) $devices->online),
                    'icon' => 'router',
                    'tone' => 'usage',
                    'delta' => null,
                    'foot' => 'of '.number_format((int) $devices->fleet).' in the fleet',
                ],
                [
                    'label' => 'Available vouchers',
                    'value' => number_format((int) $vouchers->available),
                    'icon' => 'ticket-perforated',
                    'tone' => 'money',
                    'delta' => null,
                    'foot' => number_format((int) $vouchers->in_use).' in use',
                ],
            ],
        ]);
    }

    /**
     * Percent change against yesterday, or null when there is no baseline — a
     * jump from zero is not "+100%", it is simply the first sale.
     *
     * @return array{direction: string, text: string}|null
     */
    private function delta(int $current, int $prior): ?array
    {
        if ($prior === 0 || $current === $prior) {
            return null;
        }

        $change = ($current - $prior) / $prior * 100;

        return [
            'direction' => $change > 0 ? 'up' : 'down',
            'text' => ($change > 0 ? '+' : '−').number_format(abs($change), abs($change) >= 10 ? 0 : 1).'%',
        ];
    }
}
