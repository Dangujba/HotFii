<?php

namespace App\Services\Reports;

use App\Models\NetworkDevice;
use App\Models\Organization;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class OrganizationReportService
{
    public function build(
        Organization $organization,
        Carbon $from,
        Carbon $to,
        ?NetworkDevice $router = null,
    ): array {
        $window = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
        $paidAt = 'COALESCE(paid_at, transactions.created_at)';
        $day = "DATE($paidAt)";
        $channel = "CASE
            WHEN channel = 'cash' AND reference LIKE 'HF-VCH-%' THEN 'voucher'
            WHEN channel = 'cash' THEN 'cash'
            ELSE 'online'
        END";
        $transactions = fn (): Builder => $organization->transactions()->getQuery()
            ->where('status', 'successful')
            ->when($router, fn (Builder $query) => $query->where('network_device_id', $router->id))
            ->whereBetween(DB::raw($paidAt), $window);

        $summary = $transactions()
            ->selectRaw('COUNT(*) as sales, COALESCE(SUM(gross_amount_kobo), 0) as gross_kobo')
            ->first();
        $usage = $organization->sessions()
            ->when($router, fn (Builder $query) => $query->where('network_device_id', $router->id))
            ->whereBetween(DB::raw('COALESCE(started_at, created_at)'), $window)
            ->selectRaw('COUNT(*) as sessions, COALESCE(SUM(input_bytes + output_bytes), 0) as bytes')
            ->first();
        $dailyRows = $transactions()
            ->selectRaw("$day as day, $channel as sale_channel, SUM(gross_amount_kobo) as total")
            ->groupBy(DB::raw($day), DB::raw($channel))
            ->orderBy('day')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->day)->toDateString().'|'.$row->sale_channel);
        $usageRows = $organization->sessions()
            ->when($router, fn (Builder $query) => $query->where('network_device_id', $router->id))
            ->whereBetween(DB::raw('COALESCE(started_at, created_at)'), $window)
            ->selectRaw('DATE(COALESCE(started_at, created_at)) as day, COUNT(*) as sessions, COALESCE(SUM(input_bytes + output_bytes), 0) as bytes')
            ->groupBy(DB::raw('DATE(COALESCE(started_at, created_at))'))
            ->orderBy('day')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->day)->toDateString());

        $labels = [];
        $dates = [];
        $salesSeries = ['online' => [], 'voucher' => [], 'cash' => []];
        $sessionValues = [];
        $byteValues = [];
        for ($date = $from->copy()->startOfDay(); $date->lte($to); $date->addDay()) {
            $dateKey = $date->toDateString();
            $dates[] = $dateKey;
            $labels[] = $date->format('j M');
            foreach (array_keys($salesSeries) as $channelKey) {
                $salesSeries[$channelKey][] = (int) ($dailyRows->get($dateKey.'|'.$channelKey)?->total ?? 0);
            }
            $usageDay = $usageRows->get($dateKey);
            $sessionValues[] = (int) ($usageDay?->sessions ?? 0);
            $byteValues[] = (int) ($usageDay?->bytes ?? 0);
        }

        $channelRows = $transactions()
            ->selectRaw("$channel as sale_channel, COUNT(*) as sales, SUM(gross_amount_kobo) as total")
            ->groupBy(DB::raw($channel))
            ->get()
            ->keyBy('sale_channel');
        $channels = collect([
            'online' => 'Online',
            'voucher' => 'Vouchers',
            'cash' => 'Direct cash',
        ])->map(function (string $label, string $key) use ($channelRows): array {
            $row = $channelRows->get($key);

            return [
                'key' => $key,
                'label' => $label,
                'sales' => (int) ($row?->sales ?? 0),
                'total_kobo' => (int) ($row?->total ?? 0),
            ];
        })->values();
        $topPlans = $transactions()
            ->join('access_plans', 'transactions.access_plan_id', '=', 'access_plans.id')
            ->groupBy('access_plans.name')
            ->selectRaw('access_plans.name, COUNT(*) as sales, SUM(transactions.gross_amount_kobo) as total')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return [
            'summary' => [
                'sales' => (int) ($summary?->sales ?? 0),
                'gross_kobo' => (int) ($summary?->gross_kobo ?? 0),
            ],
            'usage' => [
                'sessions' => (int) ($usage?->sessions ?? 0),
                'bytes' => (int) ($usage?->bytes ?? 0),
            ],
            'sales_trend' => [
                'dates' => $dates,
                'labels' => $labels,
                'series' => $salesSeries,
            ],
            'channels' => $channels,
            'top_plans' => $topPlans,
            'usage_trend' => [
                'dates' => $dates,
                'labels' => $labels,
                'sessions' => $sessionValues,
                'bytes' => $byteValues,
            ],
        ];
    }
}
