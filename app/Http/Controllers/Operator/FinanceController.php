<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\FeeLedgerEntry;
use App\Models\Invoice;
use App\Models\Organization;
use App\Support\ListFilters;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceController extends Controller
{
    /** A fee is accrued when the sale settles, then collected on invoice. */
    private const ENTRY_STATUSES = ['accrued', 'collected'];

    private const INVOICE_STATUSES = ['draft', 'open', 'paid'];

    public function __invoke(Request $request, Organization $organization): View
    {
        $period = now()->startOfMonth()->toDateString();

        $filters = [
            'status' => ListFilters::choice($request, 'status', self::ENTRY_STATUSES),
            'period' => ListFilters::month($request, 'period'),
            'invoice_status' => ListFilters::choice($request, 'invoice_status', self::INVOICE_STATUSES),
        ];

        return view('operator.finance', [
            'entries' => FeeLedgerEntry::where('organization_id', $organization->id)
                ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
                ->when($filters['period'], fn ($query, $month) => $query->whereBetween('billing_period', [
                    Carbon::parse($month.'-01')->startOfMonth()->toDateString(),
                    Carbon::parse($month.'-01')->endOfMonth()->toDateString(),
                ]))
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            // Its own page name, so paging invoices leaves the ledger where it is.
            'invoices' => Invoice::where('organization_id', $organization->id)
                ->when($filters['invoice_status'], fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate(12, ['*'], 'invoices')
                ->withQueryString(),
            'subscription' => $organization->subscriptions()->latest()->first(),
            // Paying moves the organization's money, so it is owner/manager
            // only. This mirrors RequireOrganizationRole exactly, impersonation
            // clause included: a platform admin who merely holds a viewer seat
            // in this organization would otherwise be shown a button that 403s.
            // Accountants and viewers see the invoice and its balance without it.
            'canPayInvoices' => ($request->user()->is_platform_admin && $request->session()->has('impersonated_organization_id'))
                || in_array($request->user()->roleFor($organization), ['owner', 'manager'], true),
            'entryStatuses' => self::ENTRY_STATUSES,
            'invoiceStatuses' => self::INVOICE_STATUSES,
            'filters' => $filters,
            'ledgerFiltered' => ListFilters::any(['status' => $filters['status'], 'period' => $filters['period']]),
            'invoicesFiltered' => $filters['invoice_status'] !== '',
            'current' => [
                'sales' => FeeLedgerEntry::where('organization_id', $organization->id)->whereDate('billing_period', $period)->sum('billable_sales_kobo'),
                'fees' => FeeLedgerEntry::where('organization_id', $organization->id)->whereDate('billing_period', $period)->sum('fee_amount_kobo'),
            ],
        ]);
    }
}
