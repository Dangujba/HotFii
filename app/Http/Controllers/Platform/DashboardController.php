<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Models\FeeLedgerEntry;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The platform overview: the whole deployment's money and tenants at a glance.
 *
 * Read-only. The organization list, the review queue and the health panel each
 * have their own page now, so this one answers a single question — is the
 * business growing and is it collecting.
 */
class DashboardController extends Controller
{
    /** Two weeks reads as a trend without the axis turning into a picket fence. */
    private const TREND_DAYS = 14;

    private const FEE_MONTHS = 6;

    public function __invoke(): View
    {
        $monthStart = CarbonImmutable::now()->startOfMonth();

        return view('platform.index', [
            'stats' => [
                'organizations' => Organization::count(),
                // Every organization is Live from registration, so the useful
                // count is how many can actually collect money.
                'collecting' => Organization::whereNotNull('live_payments_enabled_at')
                    ->whereNotNull('paystack_subaccount_code')
                    ->count(),
                'monthly_volume' => (int) Transaction::where('status', 'successful')
                    ->where('paid_at', '>=', $monthStart)
                    ->sum('gross_amount_kobo'),
                'monthly_fees' => (int) FeeLedgerEntry::whereDate('billing_period', $monthStart->toDateString())
                    ->sum('fee_amount_kobo'),
                'open_invoices' => (int) Invoice::where('status', '!=', 'paid')->sum('total_kobo'),
                'pending_reviews' => Organization::whereHas('paymentProfile', fn ($query) => $query->where('status', 'submitted'))->count(),
            ],
            'volume' => $this->volumeTrend(),
            'statusMix' => $this->statusMix(),
            'fees' => $this->feeTrend(),
            'organizations' => Organization::withCount('users')->latest()->limit(5)->get(),
            'transactions' => Transaction::with('organization')->latest()->limit(5)->get(),
        ]);
    }

    /**
     * Gross volume per day across every tenant, in naira.
     *
     * Days with no sales are filled in rather than dropped: a gap-free axis is
     * what makes the shape of the line honest.
     *
     * @return array{labels: list<string>, values: list<float>, total: float}
     */
    private function volumeTrend(): array
    {
        $start = CarbonImmutable::today()->subDays(self::TREND_DAYS - 1);

        $sums = Transaction::where('status', 'successful')
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

        return ['labels' => $labels, 'values' => $values, 'total' => array_sum($values)];
    }

    /**
     * Every account status with its count, in the enum's own order.
     *
     * All seven are returned even at zero: a status that empties out should
     * leave a gap on the axis rather than shuffling the ones that remain into
     * different positions from one day to the next.
     *
     * @return array{rows: list<array{value: string, label: string, count: int}>, labels: list<string>, values: list<int>, total: int}
     */
    private function statusMix(): array
    {
        $counts = Organization::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            // pluck() casts the value column but never the key, so these stay
            // raw strings and match the enum-backed values below directly.
            ->pluck('total', 'status');

        $rows = [];

        foreach (OrganizationStatus::cases() as $status) {
            $rows[] = [
                'value' => $status->value,
                'label' => str_replace('_', ' ', ucfirst($status->value)),
                'count' => (int) ($counts[$status->value] ?? 0),
            ];
        }

        return [
            'rows' => $rows,
            // Reversed, so the first enum case sits at the top of a horizontal bar.
            'labels' => array_reverse(array_column($rows, 'label')),
            'values' => array_reverse(array_column($rows, 'count')),
            'total' => array_sum(array_column($rows, 'count')),
        ];
    }

    /**
     * Fees accrued against fees collected, by billing period.
     *
     * The gap between the two lines is the platform's outstanding balance: a fee
     * accrues when a sale settles and is collected either by the Paystack split
     * or by paying the monthly invoice.
     *
     * @return array{labels: list<string>, accrued: list<float>, collected: list<float>, accrued_total: float, collected_total: float}
     */
    private function feeTrend(): array
    {
        $start = CarbonImmutable::now()->startOfMonth()->subMonths(self::FEE_MONTHS - 1);

        $sums = FeeLedgerEntry::whereDate('billing_period', '>=', $start->toDateString())
            ->selectRaw('billing_period, status, SUM(fee_amount_kobo) as kobo')
            ->groupBy('billing_period', 'status')
            ->get()
            ->groupBy(fn (FeeLedgerEntry $entry) => $entry->billing_period->format('Y-m'));

        $labels = [];
        $accrued = [];
        $collected = [];

        for ($offset = 0; $offset < self::FEE_MONTHS; $offset++) {
            $month = $start->addMonths($offset);
            $entries = $sums->get($month->format('Y-m'), collect());

            $labels[] = $month->format('M Y');
            // "Accrued" is the whole fee earned in the period, collected or not,
            // so a collected fee counts towards both bars rather than moving
            // between them.
            $accrued[] = round((int) $entries->sum('kobo') / 100, 2);
            $collected[] = round((int) $entries->where('status', 'collected')->sum('kobo') / 100, 2);
        }

        return [
            'labels' => $labels,
            'accrued' => $accrued,
            'collected' => $collected,
            'accrued_total' => array_sum($accrued),
            'collected_total' => array_sum($collected),
        ];
    }
}
