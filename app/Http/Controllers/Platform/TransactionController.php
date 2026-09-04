<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Transaction;
use App\Support\ListFilters;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Every payment on the deployment, in one list.
 *
 * Read-only. This exists so a dispute — "a customer says they paid and got
 * nothing" — can be answered by reference lookup instead of by SQL on the
 * production database.
 */
class TransactionController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = [
            'search' => ListFilters::text($request, 'search'),
            'organization' => ListFilters::id($request, 'organization'),
            'status' => ListFilters::choice($request, 'status', ListFilters::enumValues(PaymentStatus::class)),
            'from' => ListFilters::date($request, 'from'),
            'to' => ListFilters::date($request, 'to'),
        ];

        $transactions = $this->query($filters);

        return view('platform.transactions', [
            'transactions' => (clone $transactions)
                ->with(['organization', 'accessPlan'])
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            // Summed over the filter, not the page: a total that changes when you
            // click "next" is worse than no total.
            'summary' => [
                'count' => (clone $transactions)->count(),
                'gross' => (int) (clone $transactions)->sum('gross_amount_kobo'),
                'fees' => (int) (clone $transactions)->sum('platform_fee_kobo'),
            ],
            'organizations' => Organization::orderBy('name')->get(['id', 'name']),
            'statuses' => PaymentStatus::cases(),
            'filters' => $filters,
            'filtered' => ListFilters::any($filters),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function query(array $filters): \Illuminate\Database\Eloquent\Builder
    {
        return Transaction::query()
            ->when($filters['search'], fn ($query, $term) => $query->where('reference', 'like', "%{$term}%"))
            ->when($filters['organization'], fn ($query, $id) => $query->where('organization_id', $id))
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['from'], fn ($query, $date) => $query->where('created_at', '>=', CarbonImmutable::parse($date)->startOfDay()))
            ->when($filters['to'], fn ($query, $date) => $query->where('created_at', '<=', CarbonImmutable::parse($date)->endOfDay()));
    }
}
