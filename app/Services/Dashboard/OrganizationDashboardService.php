<?php

namespace App\Services\Dashboard;

use App\Models\NetworkDevice;
use App\Models\Organization;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class OrganizationDashboardService
{
    public const TREND_DAYS = 14;

    public const PLAN_WINDOW_DAYS = 30;

    private const TOP_PLANS = 6;

    /** @var array<string, array{0: string, 1: string, 2: string}> */
    private const FLEET_STATES = [
        'online' => ['Online', 'good', 'check-circle-fill'],
        'testing' => ['Testing', 'warning', 'activity'],
        'pending' => ['Pending', 'idle', 'hourglass-split'],
        'offline' => ['Offline', 'serious', 'dash-circle-fill'],
        'failed' => ['Failed', 'critical', 'exclamation-triangle-fill'],
    ];

    /** @return array<string, int|array{direction: string, text: string}|null> */
    public function pulse(Organization $organization, ?int $routerId = null): array
    {
        $today = CarbonImmutable::today();
        $yesterday = $today->subDay();
        $window = [$yesterday->startOfDay(), $today->endOfDay()];
        $days = [$today->toDateString(), $yesterday->toDateString()];

        $money = $organization->transactions()
            ->where('status', 'successful')
            ->when($routerId, fn ($query, $router) => $query->where('network_device_id', $router))
            ->whereBetween('paid_at', $window)
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN DATE(paid_at) = ? THEN gross_amount_kobo ELSE 0 END), 0) as today_kobo,
                 COALESCE(SUM(CASE WHEN DATE(paid_at) = ? THEN gross_amount_kobo ELSE 0 END), 0) as prior_kobo,
                 COALESCE(SUM(CASE WHEN DATE(paid_at) = ? THEN 1 ELSE 0 END), 0) as today_sales,
                 COALESCE(SUM(CASE WHEN DATE(paid_at) = ? THEN 1 ELSE 0 END), 0) as prior_sales',
                [...$days, ...$days]
            )
            ->first();

        $active = $organization->sessions()
            ->where('status', 'active')
            ->when($routerId, fn ($query, $router) => $query->where('network_device_id', $router))
            ->count();

        $starts = $organization->sessions()
            ->when($routerId, fn ($query, $router) => $query->where('network_device_id', $router))
            ->whereBetween('started_at', $window)
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN DATE(started_at) = ? THEN 1 ELSE 0 END), 0) as today_starts,
                 COALESCE(SUM(CASE WHEN DATE(started_at) = ? THEN 1 ELSE 0 END), 0) as prior_starts',
                $days
            )
            ->first();

        $devices = $organization->networkDevices()
            ->when($routerId, fn ($query, $router) => $query->whereKey($router))
            ->selectRaw("COUNT(*) as fleet, COALESCE(SUM(CASE WHEN status = 'online' THEN 1 ELSE 0 END), 0) as online")
            ->first();

        $vouchers = $organization->vouchers()
            ->when($routerId, fn ($query, $router) => $query->where(function ($inner) use ($router) {
                $inner->where('network_device_id', $router)->orWhereNull('network_device_id');
            }))
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN status IN ('generated', 'printed', 'assigned', 'sold') THEN 1 ELSE 0 END), 0) as available,
                 COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) as in_use"
            )
            ->first();

        $todayRevenue = (int) $money->today_kobo;
        $priorRevenue = (int) $money->prior_kobo;
        $todaySales = (int) $money->today_sales;
        $priorSales = (int) $money->prior_sales;
        $todayStarts = (int) $starts->today_starts;
        $priorStarts = (int) $starts->prior_starts;

        return [
            'revenue_today_kobo' => $todayRevenue,
            'revenue_yesterday_kobo' => $priorRevenue,
            'revenue_delta' => $this->delta($todayRevenue, $priorRevenue),
            'sales_today' => $todaySales,
            'sales_yesterday' => $priorSales,
            'sales_delta' => $this->delta($todaySales, $priorSales),
            'active_sessions' => $active,
            'sessions_started_today' => $todayStarts,
            'sessions_started_yesterday' => $priorStarts,
            'sessions_delta' => $this->delta($todayStarts, $priorStarts),
            'online_routers' => (int) $devices->online,
            'total_routers' => (int) $devices->fleet,
            'available_vouchers' => (int) $vouchers->available,
            'vouchers_in_use' => (int) $vouchers->in_use,
        ];
    }

    /** @return array{labels: list<string>, values: list<float>, total: float, best: float} */
    public function revenueTrend(Organization $organization, ?int $routerId = null): array
    {
        $start = CarbonImmutable::today()->subDays(self::TREND_DAYS - 1);
        $sums = $organization->transactions()
            ->where('status', 'successful')
            ->when($routerId, fn ($query, $router) => $query->where('network_device_id', $router))
            ->whereBetween('paid_at', [$start->startOfDay(), CarbonImmutable::today()->endOfDay()])
            ->selectRaw('DATE(paid_at) as day, SUM(gross_amount_kobo) as kobo')
            ->groupBy(DB::raw('DATE(paid_at)'))
            ->pluck('kobo', 'day')
            ->mapWithKeys(fn ($kobo, $day) => [CarbonImmutable::parse($day)->toDateString() => (int) $kobo]);

        $labels = [];
        $values = [];
        for ($offset = 0; $offset < self::TREND_DAYS; $offset++) {
            $day = $start->addDays($offset);
            $labels[] = $day->format('j M');
            $values[] = round(($sums[$day->toDateString()] ?? 0) / 100, 2);
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'total' => array_sum($values),
            'best' => $values === [] ? 0.0 : max($values),
        ];
    }

    /** @return array{rows: list<array{key: string, label: string, tone: string, icon: string, value: int}>, slices: list<array{label: string, tone: string, value: int}>, total: int} */
    public function fleet(Organization $organization, ?int $routerId = null): array
    {
        $counts = $organization->networkDevices()
            ->when($routerId, fn ($query, $router) => $query->whereKey($router))
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $rows = [];
        foreach (self::FLEET_STATES as $key => [$label, $tone, $icon]) {
            $rows[] = compact('key', 'label', 'tone', 'icon') + ['value' => (int) ($counts[$key] ?? 0)];
        }

        return [
            'rows' => $rows,
            'slices' => array_values(array_map(
                fn (array $row) => ['label' => $row['label'], 'tone' => $row['tone'], 'value' => $row['value']],
                array_filter($rows, fn (array $row) => $row['value'] > 0)
            )),
            'total' => array_sum(array_column($rows, 'value')),
        ];
    }

    /** @return array{labels: list<string>, values: list<int>, total: int, peak: ?array{hour: int, value: int}} */
    public function hourly(Organization $organization, ?int $routerId = null): array
    {
        $expression = match (DB::connection()->getDriverName()) {
            'pgsql' => 'EXTRACT(HOUR FROM started_at)',
            'sqlite' => "CAST(strftime('%H', started_at) AS INTEGER)",
            default => 'HOUR(started_at)',
        };
        $counts = $organization->sessions()
            ->when($routerId, fn ($query, $router) => $query->where('network_device_id', $router))
            ->whereBetween('started_at', [CarbonImmutable::today()->startOfDay(), CarbonImmutable::today()->endOfDay()])
            ->selectRaw("$expression as hour, COUNT(*) as total")
            ->groupBy(DB::raw($expression))
            ->pluck('total', 'hour')
            ->mapWithKeys(fn ($total, $hour) => [(int) $hour => (int) $total]);

        $labels = [];
        $values = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $labels[] = str_pad((string) $hour, 2, '0', STR_PAD_LEFT);
            $values[] = $counts[$hour] ?? 0;
        }
        $peak = max($values);

        return [
            'labels' => $labels,
            'values' => $values,
            'total' => array_sum($values),
            'peak' => $peak > 0 ? ['hour' => (int) array_search($peak, $values, true), 'value' => $peak] : null,
        ];
    }

    /** @return array{labels: list<string>, values: list<float>, days: int} */
    public function topPlans(Organization $organization, ?int $routerId = null): array
    {
        $plans = $organization->transactions()
            ->join('access_plans', 'transactions.access_plan_id', '=', 'access_plans.id')
            ->where('transactions.status', 'successful')
            ->when($routerId, fn ($query, $router) => $query->where('transactions.network_device_id', $router))
            ->whereBetween('transactions.created_at', [
                CarbonImmutable::today()->subDays(self::PLAN_WINDOW_DAYS - 1)->startOfDay(),
                CarbonImmutable::today()->endOfDay(),
            ])
            ->groupBy('access_plans.name')
            ->selectRaw('access_plans.name, SUM(transactions.gross_amount_kobo) as kobo')
            ->orderByDesc('kobo')
            ->limit(self::TOP_PLANS)
            ->get();

        return [
            'labels' => $plans->pluck('name')->reverse()->values()->all(),
            'values' => $plans->pluck('kobo')->reverse()->values()->map(fn ($kobo) => round((int) $kobo / 100, 2))->all(),
            'days' => self::PLAN_WINDOW_DAYS,
        ];
    }

    /** @return Collection<int, NetworkDevice> */
    public function recentDevices(Organization $organization, ?int $routerId = null): Collection
    {
        return $organization->networkDevices()
            ->with('location')
            ->when($routerId, fn ($query, $router) => $query->whereKey($router))
            ->latest()
            ->limit(6)
            ->get();
    }

    /** @return Collection<int, Transaction> */
    public function recentTransactions(Organization $organization, ?int $routerId = null): Collection
    {
        return $organization->transactions()
            ->with('networkDevice')
            ->when($routerId, fn ($query, $router) => $query->where('network_device_id', $router))
            ->latest()
            ->limit(6)
            ->get();
    }

    /** @return array{direction: string, text: string}|null */
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
