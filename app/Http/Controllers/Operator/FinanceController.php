<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\FeeLedgerEntry;
use App\Models\Invoice;
use App\Models\Organization;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function __invoke(Organization $organization): View
    {
        $period = now()->startOfMonth()->toDateString();

        return view('operator.finance', [
            'entries' => FeeLedgerEntry::where('organization_id', $organization->id)->latest()->paginate(25),
            'invoices' => Invoice::where('organization_id', $organization->id)->latest()->limit(12)->get(),
            'subscription' => $organization->subscriptions()->latest()->first(),
            'current' => [
                'sales' => FeeLedgerEntry::where('organization_id', $organization->id)->whereDate('billing_period', $period)->sum('billable_sales_kobo'),
                'fees' => FeeLedgerEntry::where('organization_id', $organization->id)->whereDate('billing_period', $period)->sum('fee_amount_kobo'),
            ],
        ]);
    }
}