<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Enums\NetworkDeviceStatus;
use App\Domain\Enums\OrganizationStatus;
use App\Domain\Enums\RouterVendor;
use App\Domain\Enums\VoucherStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\FeeLedgerEntry;
use App\Models\HotspotSession;
use App\Models\Invoice;
use App\Models\NetworkDevice;
use App\Models\Organization;
use App\Models\Transaction;
use App\Models\Voucher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const TREND_DAYS = 14;
    private const FEE_MONTHS = 6;

    public function __invoke(): View
    {
        $monthStart =
            CarbonImmutable::now()
                ->startOfMonth();

        $network =
            $this->networkSummary();

        return view('platform.index', [
            'stats' => [
                'organizations' =>
                    Organization::count(),

                'customers' =>
                    Customer::count(),

                'routers' =>
                    NetworkDevice::count(),

                'online_routers' =>
                    NetworkDevice::where(
                        'status',
                        NetworkDeviceStatus::Online->value
                    )->count(),

                'live_sessions' =>
                    $network['live_sessions'],

                'vouchers' =>
                    Voucher::count(),

                'data_bytes' =>
                    $network['total_bytes'],

                'collecting' =>
                    Organization::whereNotNull(
                        'live_payments_enabled_at'
                    )
                        ->whereNotNull(
                            'paystack_subaccount_code'
                        )
                        ->count(),

                'monthly_volume' =>
                    (int) Transaction::where(
                        'status',
                        'successful'
                    )
                        ->where(
                            'paid_at',
                            '>=',
                            $monthStart
                        )
                        ->sum(
                            'gross_amount_kobo'
                        ),

                'monthly_fees' =>
                    (int) FeeLedgerEntry::whereDate(
                        'billing_period',
                        $monthStart->toDateString()
                    )
                        ->sum(
                            'fee_amount_kobo'
                        ),

                'open_invoices' =>
                    (int) Invoice::where(
                        'status',
                        '!=',
                        'paid'
                    )
                        ->sum(
                            'total_kobo'
                        ),

                'pending_reviews' =>
                    Organization::whereHas(
                        'paymentProfile',
                        fn ($query) =>
                            $query->where(
                                'status',
                                'submitted'
                            )
                    )->count(),
            ],

            'network' =>
                $network,

            'routerStatus' =>
                $this->routerStatusMix(),

            'vendorMix' =>
                $this->vendorMix(),

            'voucherStatus' =>
                $this->voucherStatusMix(),

            'volume' =>
                $this->volumeTrend(),

            'statusMix' =>
                $this->statusMix(),

            'fees' =>
                $this->feeTrend(),

            'organizations' =>
                Organization::withCount('users')
                    ->latest()
                    ->limit(5)
                    ->get(),

            'transactions' =>
                Transaction::with('organization')
                    ->latest()
                    ->limit(5)
                    ->get(),

            'topOrganizations' =>
                $this->topOrganizations(),

            'recentRouters' =>
                NetworkDevice::query()
                    ->with('organization')
                    ->withCount([
                        'sessions as active_sessions_count' =>
                            fn ($query) =>
                                $query->whereIn(
                                    'status',
                                    [
                                        'active',
                                        'disconnect_pending',
                                    ]
                                ),
                    ])
                    ->orderByRaw(
                        'last_heartbeat_at DESC NULLS LAST'
                    )
                    ->limit(8)
                    ->get(),
        ]);
    }

    private function networkSummary(): array
    {
        $input =
            (int) HotspotSession::sum(
                'input_bytes'
            );

        $output =
            (int) HotspotSession::sum(
                'output_bytes'
            );

        return [
            'input_bytes' =>
                $input,

            'output_bytes' =>
                $output,

            'total_bytes' =>
                $input + $output,

            'sessions_total' =>
                HotspotSession::count(),

            'live_sessions' =>
                HotspotSession::whereIn(
                    'status',
                    [
                        'active',
                        'disconnect_pending',
                    ]
                )->count(),

            'sessions_today' =>
                HotspotSession::where(
                    'started_at',
                    '>=',
                    CarbonImmutable::today()
                )->count(),
        ];
    }

    private function routerStatusMix(): array
    {
        $counts =
            NetworkDevice::selectRaw(
                'status, COUNT(*) as total'
            )
                ->groupBy('status')
                ->pluck(
                    'total',
                    'status'
                );

        $rows = [];

        foreach (
            NetworkDeviceStatus::cases()
            as $status
        ) {
            $rows[] = [
                'value' =>
                    $status->value,

                'label' =>
                    ucfirst(
                        $status->value
                    ),

                'count' =>
                    (int) (
                        $counts[
                            $status->value
                        ]
                        ?? 0
                    ),
            ];
        }

        return [
            'rows' => $rows,
            'total' =>
                array_sum(
                    array_column(
                        $rows,
                        'count'
                    )
                ),
        ];
    }

    private function vendorMix(): array
    {
        $counts =
            NetworkDevice::selectRaw(
                'vendor, COUNT(*) as total'
            )
                ->groupBy('vendor')
                ->pluck(
                    'total',
                    'vendor'
                );

        $rows = [];

        foreach (
            RouterVendor::cases()
            as $vendor
        ) {
            $count =
                (int) (
                    $counts[$vendor->value]
                    ?? 0
                );

            if ($count === 0) {
                continue;
            }

            $rows[] = [
                'value' =>
                    $vendor->value,

                'label' =>
                    $vendor->label(),

                'count' =>
                    $count,
            ];
        }

        return $rows;
    }

    private function voucherStatusMix(): array
    {
        $counts =
            Voucher::selectRaw(
                'status, COUNT(*) as total'
            )
                ->groupBy('status')
                ->pluck(
                    'total',
                    'status'
                );

        /*
         * "Sold" is historical/cumulative, not a current-state count.
         * A voucher normally becomes Active after redemption, so counting
         * only rows whose current status is "sold" incorrectly reports zero.
         */
        $soldCount =
            Voucher::query()
                ->whereNotNull('sold_at')
                ->where(
                    'is_complimentary',
                    false
                )
                ->count();

        $rows = [];

        foreach (
            VoucherStatus::cases()
            as $status
        ) {
            $rows[] = [
                'value' =>
                    $status->value,

                'label' =>
                    $status === VoucherStatus::Sold
                        ? 'Sold (cumulative)'
                        : ucfirst(
                            $status->value
                        ),

                'count' =>
                    $status === VoucherStatus::Sold
                        ? $soldCount
                        : (int) (
                            $counts[
                                $status->value
                            ]
                            ?? 0
                        ),
            ];
        }

        return [
            'rows' =>
                $rows,

            'total' =>
                Voucher::count(),

            'complimentary' =>
                Voucher::where(
                    'is_complimentary',
                    true
                )->count(),

            'activated_today' =>
                Voucher::where(
                    'activated_at',
                    '>=',
                    CarbonImmutable::today()
                )->count(),
        ];
    }

    private function topOrganizations()
    {
        return Organization::query()
            ->select('organizations.*')

            ->selectSub(
                NetworkDevice::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn(
                        'network_devices.organization_id',
                        'organizations.id'
                    ),
                'routers_count'
            )

            ->selectSub(
                NetworkDevice::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn(
                        'network_devices.organization_id',
                        'organizations.id'
                    )
                    ->where(
                        'status',
                        'online'
                    ),
                'online_routers_count'
            )

            ->selectSub(
                HotspotSession::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn(
                        'hotspot_sessions.organization_id',
                        'organizations.id'
                    )
                    ->whereIn(
                        'status',
                        [
                            'active',
                            'disconnect_pending',
                        ]
                    ),
                'live_sessions_count'
            )

            ->selectSub(
                HotspotSession::query()
                    ->selectRaw(
                        'COALESCE(SUM(input_bytes + output_bytes), 0)'
                    )
                    ->whereColumn(
                        'hotspot_sessions.organization_id',
                        'organizations.id'
                    ),
                'data_bytes'
            )

            ->selectSub(
                Transaction::query()
                    ->selectRaw(
                        'COALESCE(SUM(gross_amount_kobo), 0)'
                    )
                    ->whereColumn(
                        'transactions.organization_id',
                        'organizations.id'
                    )
                    ->where(
                        'status',
                        'successful'
                    ),
                'volume_kobo'
            )

            ->orderByDesc('data_bytes')
            ->orderByDesc('volume_kobo')
            ->limit(6)
            ->get();
    }

    private function volumeTrend(): array
    {
        $start =
            CarbonImmutable::today()
                ->subDays(
                    self::TREND_DAYS - 1
                );

        $sums =
            Transaction::where(
                'status',
                'successful'
            )
                ->whereBetween(
                    'paid_at',
                    [
                        $start->startOfDay(),
                        CarbonImmutable::today()
                            ->endOfDay(),
                    ]
                )
                ->selectRaw(
                    'DATE(paid_at) as day, SUM(gross_amount_kobo) as kobo'
                )
                ->groupBy(
                    DB::raw(
                        'DATE(paid_at)'
                    )
                )
                ->pluck(
                    'kobo',
                    'day'
                )
                ->mapWithKeys(
                    fn ($kobo, $day) => [
                        CarbonImmutable::parse(
                            $day
                        )->toDateString()
                            => (int) $kobo,
                    ]
                );

        $labels = [];
        $values = [];

        for (
            $offset = 0;
            $offset < self::TREND_DAYS;
            $offset++
        ) {
            $day =
                $start->addDays(
                    $offset
                );

            $labels[] =
                $day->format('j M');

            $values[] =
                round(
                    (
                        $sums[
                            $day->toDateString()
                        ]
                        ?? 0
                    ) / 100,
                    2
                );
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'total' => array_sum($values),
        ];
    }

    private function statusMix(): array
    {
        $counts =
            Organization::selectRaw(
                'status, COUNT(*) as total'
            )
                ->groupBy('status')
                ->pluck(
                    'total',
                    'status'
                );

        $rows = [];

        foreach (
            OrganizationStatus::cases()
            as $status
        ) {
            $rows[] = [
                'value' =>
                    $status->value,

                'label' =>
                    str_replace(
                        '_',
                        ' ',
                        ucfirst(
                            $status->value
                        )
                    ),

                'count' =>
                    (int) (
                        $counts[
                            $status->value
                        ]
                        ?? 0
                    ),
            ];
        }

        return [
            'rows' => $rows,

            'labels' =>
                array_reverse(
                    array_column(
                        $rows,
                        'label'
                    )
                ),

            'values' =>
                array_reverse(
                    array_column(
                        $rows,
                        'count'
                    )
                ),

            'total' =>
                array_sum(
                    array_column(
                        $rows,
                        'count'
                    )
                ),
        ];
    }

    private function feeTrend(): array
    {
        $start =
            CarbonImmutable::now()
                ->startOfMonth()
                ->subMonths(
                    self::FEE_MONTHS - 1
                );

        $sums =
            FeeLedgerEntry::whereDate(
                'billing_period',
                '>=',
                $start->toDateString()
            )
                ->selectRaw(
                    'billing_period, status, SUM(fee_amount_kobo) as kobo'
                )
                ->groupBy(
                    'billing_period',
                    'status'
                )
                ->get()
                ->groupBy(
                    fn (
                        FeeLedgerEntry $entry
                    ) =>
                        $entry
                            ->billing_period
                            ->format('Y-m')
                );

        $labels = [];
        $accrued = [];
        $collected = [];

        for (
            $offset = 0;
            $offset < self::FEE_MONTHS;
            $offset++
        ) {
            $month =
                $start->addMonths(
                    $offset
                );

            $entries =
                $sums->get(
                    $month->format('Y-m'),
                    collect()
                );

            $labels[] =
                $month->format('M Y');

            $accrued[] =
                round(
                    (int) $entries
                        ->sum('kobo')
                    / 100,
                    2
                );

            $collected[] =
                round(
                    (int) $entries
                        ->where(
                            'status',
                            'collected'
                        )
                        ->sum('kobo')
                    / 100,
                    2
                );
        }

        return [
            'labels' =>
                $labels,

            'accrued' =>
                $accrued,

            'collected' =>
                $collected,

            'accrued_total' =>
                array_sum(
                    $accrued
                ),

            'collected_total' =>
                array_sum(
                    $collected
                ),
        ];
    }
}
