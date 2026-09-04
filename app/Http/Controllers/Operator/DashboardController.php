<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Two weeks reads as a trend without the axis turning into a picket fence. */
    private const TREND_DAYS = 14;

    private const PLAN_WINDOW_DAYS = 30;

    private const TOP_PLANS = 6;

    /**
     * Router states in a fixed order with a fixed tone. Colour follows the state
     * itself, so a state falling to zero leaves the ring rather than repainting
     * the states that remain.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const FLEET_STATES = [
        'online' => ['Online', 'good', 'check-circle-fill'],
        'testing' => ['Testing', 'warning', 'activity'],
        'pending' => ['Pending', 'idle', 'hourglass-split'],
        'offline' => ['Offline', 'serious', 'dash-circle-fill'],
        'failed' => ['Failed', 'critical', 'exclamation-triangle-fill'],
    ];

    public function __invoke(Organization $organization): View
    {
        return view('dashboard.index', [
            'revenue' => $this->revenueTrend($organization),
            'fleet' => $this->fleet($organization),
            'hourly' => $this->hourly($organization),
            'plans' => $this->topPlans($organization),
            'devices' => $organization->networkDevices()->with('location')->latest()->limit(6)->get(),
            'transactions' => $organization->transactions()->latest()->limit(6)->get(),
        ]);
    }

    /**
     * Revenue per day over the trailing fortnight, in naira.
     *
     * Days with no sales are filled in rather than dropped: a gap-free axis is
     * what makes the shape of the line honest.
     *
     * @return array{labels: list<string>, values: list<float>, total: float, best: float}
     */
    private function revenueTrend(Organization $organization): array
    {
        $start = CarbonImmutable::today()->subDays(self::TREND_DAYS - 1);

        $sums = $organization->transactions()
            ->where('status', 'successful')
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

    /**
     * Every router state with its count. All five are returned even at zero, so
     * the card's legend can print a complete picture; only the non-zero ones are
     * handed to the ring.
     *
     * @return array{rows: list<array{key: string, label: string, tone: string, icon: string, value: int}>, slices: list<array{label: string, tone: string, value: int}>, total: int}
     */
    private function fleet(Organization $organization): array
    {
        $counts = $organization->networkDevices()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            // pluck() casts the value column but never the key, so these stay
            // raw strings and match the enum-backed keys below directly.
            ->pluck('total', 'status');

        $rows = [];

        foreach (self::FLEET_STATES as $key => [$label, $tone, $icon]) {
            $rows[] = [
                'key' => $key,
                'label' => $label,
                'tone' => $tone,
                'icon' => $icon,
                'value' => (int) ($counts[$key] ?? 0),
            ];
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

    /**
     * Session starts by hour of the current day.
     *
     * @return array{labels: list<string>, values: list<int>, total: int, peak: ?array{hour: int, value: int}}
     */
    private function hourly(Organization $organization): array
    {
        // Hour extraction is the one piece of this dashboard that no two database
        // engines spell the same way.
        $expression = match (DB::connection()->getDriverName()) {
            'pgsql' => 'EXTRACT(HOUR FROM started_at)',
            'sqlite' => "CAST(strftime('%H', started_at) AS INTEGER)",
            default => 'HOUR(started_at)',
        };

        $counts = $organization->sessions()
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

    /**
     * The plans actually earning money this month, in naira.
     *
     * @return array{labels: list<string>, values: list<float>, days: int}
     */
    private function topPlans(Organization $organization): array
    {
        $plans = $organization->transactions()
            ->join('access_plans', 'transactions.access_plan_id', '=', 'access_plans.id')
            ->where('transactions.status', 'successful')
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
            // Biggest at the top of a horizontal bar chart, so the axis reads
            // downward the way the ranking does.
            'labels' => $plans->pluck('name')->reverse()->values()->all(),
            'values' => $plans->pluck('kobo')->reverse()->values()->map(fn ($kobo) => round((int) $kobo / 100, 2))->all(),
            'days' => self::PLAN_WINDOW_DAYS,
        ];
    }
}
