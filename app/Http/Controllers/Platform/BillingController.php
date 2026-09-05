<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\FeeLedgerEntry;
use App\Models\Invoice;
use App\Support\ListFilters;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * What the platform has earned, and what is still owed.
 *
 * Read-only by design. Marking an invoice paid or re-running the invoice job
 * from a web page would change what a customer owes with no second pair of
 * eyes; both stay on the server, where they leave a shell record.
 */
class BillingController extends Controller
{
    private const HISTORY_MONTHS = 12;

    private const INVOICE_STATUSES = ['draft', 'open', 'paid'];

    /** Enough to see who the platform's revenue actually comes from. */
    private const TOP_ORGANIZATIONS = 25;

    public function __invoke(Request $request): View
    {
        $filters = [
            'period' => ListFilters::month($request, 'period'),
            'invoice_status' => ListFilters::choice($request, 'invoice_status', self::INVOICE_STATUSES),
            'invoice_period' => ListFilters::month($request, 'invoice_period'),
        ];

        $selected = CarbonImmutable::parse(($filters['period'] ?? CarbonImmutable::now()->format('Y-m')).'-01')->startOfMonth();

        return view('platform.billing', [
            'months' => $this->history(),
            'selected' => $selected,
            'breakdown' => $this->breakdown($selected),
            'invoices' => Invoice::query()
                ->with('organization')
                ->when($filters['invoice_status'], fn ($query, $status) => $query->where('status', $status))
                ->when($filters['invoice_period'], fn ($query, $month) => $query->whereDate(
                    'billing_period',
                    CarbonImmutable::parse($month.'-01')->startOfMonth()->toDateString(),
                ))
                ->latest()
                // Its own page name, so paging invoices leaves the month table alone.
                ->paginate(20, ['*'], 'invoices')
                ->withQueryString(),
            'invoiceTotals' => [
                'open' => (int) Invoice::where('status', '!=', 'paid')->sum('total_kobo'),
                'paid' => (int) Invoice::where('status', 'paid')->sum('total_kobo'),
                'count' => Invoice::count(),
            ],
            'invoiceStatuses' => self::INVOICE_STATUSES,
            'filters' => $filters,
            'invoicesFiltered' => ListFilters::any([
                'invoice_status' => $filters['invoice_status'],
                'invoice_period' => $filters['invoice_period'],
            ]),
            // Read straight from configuration. Changing a price means editing
            // .env on the server and rebuilding, which is the point: it is not a
            // thing to fat-finger from a browser.
            'pricing' => [
                'fee_bps' => (int) config('hotfii.commerce.platform_fee_bps'),
                'standard_minimum_kobo' => (int) config('hotfii.commerce.standard_minimum_kobo'),
                'minimum_included_sales_kobo' => (int) config('hotfii.commerce.minimum_included_sales_kobo'),
                'micro_sales_limit_kobo' => (int) config('hotfii.commerce.micro_sales_limit_kobo'),
                'trial_sales_cap_kobo' => (int) config('hotfii.commerce.trial_sales_cap_kobo'),
                'trial_days' => (int) config('hotfii.commerce.trial_days'),
                'grace_days' => (int) config('hotfii.commerce.grace_days'),
            ],
            'internalPlans' => (array) config('hotfii.internal_plans'),
        ]);
    }

    /**
     * Sales, fees earned and fees collected for each of the last twelve periods.
     *
     * Months with no ledger activity are kept, so a quiet month reads as a zero
     * row rather than vanishing from the table.
     *
     * @return list<array{key: string, label: string, sales: int, fees: int, collected: int, outstanding: int}>
     */
    private function history(): array
    {
        $start = CarbonImmutable::now()->startOfMonth()->subMonths(self::HISTORY_MONTHS - 1);

        $grouped = FeeLedgerEntry::whereDate('billing_period', '>=', $start->toDateString())
            ->selectRaw('billing_period, status, SUM(billable_sales_kobo) as sales_kobo, SUM(fee_amount_kobo) as fee_kobo')
            ->groupBy('billing_period', 'status')
            ->get()
            ->groupBy(fn (FeeLedgerEntry $entry) => $entry->billing_period->format('Y-m'));

        $months = [];

        for ($offset = 0; $offset < self::HISTORY_MONTHS; $offset++) {
            $month = $start->addMonths($offset);
            $entries = $grouped->get($month->format('Y-m'), collect());

            $fees = (int) $entries->sum('fee_kobo');
            $collected = (int) $entries->where('status', 'collected')->sum('fee_kobo');

            $months[] = [
                'key' => $month->format('Y-m'),
                'label' => $month->format('M Y'),
                'sales' => (int) $entries->sum('sales_kobo'),
                'fees' => $fees,
                'collected' => $collected,
                'outstanding' => max(0, $fees - $collected),
            ];
        }

        // Newest first: the current month is what somebody opened this page for.
        return array_reverse($months);
    }

    /**
     * Who the selected month's fees came from.
     *
     * @return \Illuminate\Support\Collection<int, FeeLedgerEntry>
     */
    private function breakdown(CarbonImmutable $month): \Illuminate\Support\Collection
    {
        return FeeLedgerEntry::query()
            ->whereDate('billing_period', $month->toDateString())
            ->selectRaw('organization_id, SUM(billable_sales_kobo) as sales_kobo, SUM(fee_amount_kobo) as fee_kobo')
            ->selectRaw("SUM(CASE WHEN status = 'collected' THEN fee_amount_kobo ELSE 0 END) as collected_kobo")
            ->groupBy('organization_id')
            ->orderByDesc('fee_kobo')
            ->limit(self::TOP_ORGANIZATIONS)
            ->with('organization')
            ->get();
    }
}
