<?php

namespace App\Http\Controllers\Operator;

use App\Domain\Enums\VoucherPinFormat;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\VoucherBatch;
use App\Services\Vouchers\VoucherService;
use App\Support\ListFilters;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VoucherBatchController extends Controller
{
    /** A batch is minted, then printed. Individual vouchers carry their own status. */
    private const BATCH_STATUSES = ['generated', 'printed'];

    public function index(Request $request, Organization $organization): View
    {
        $filters = [
            'search' => ListFilters::text($request, 'search'),
            'status' => ListFilters::choice($request, 'status', self::BATCH_STATUSES),
            'plan' => ListFilters::id($request, 'plan'),
        ];

        return view('operator.vouchers', [
            'batches' => $organization->voucherBatches()
                ->with('accessPlan')
                ->when($filters['search'], fn ($query, $term) => $query->where('reference', 'like', "%{$term}%"))
                ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
                ->when($filters['plan'], fn ($query, $plan) => $query->where('access_plan_id', $plan))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'plans' => $organization->accessPlans()->where('is_active', true)->where('access_type', 'paid')->orderBy('name')->get(),
            // Batches outlive the plans they were minted from, so the filter
            // list is not the same as the list you can generate against.
            'filterPlans' => $organization->accessPlans()->orderBy('name')->get(['id', 'name']),
            'pinFormats' => VoucherPinFormat::cases(),
            'statuses' => self::BATCH_STATUSES,
            'filters' => $filters,
            'filtered' => ListFilters::any($filters),
        ]);
    }

    public function store(Request $request, Organization $organization, VoucherService $service): RedirectResponse
    {
        $data = $request->validate([
            'access_plan_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:5000'],
            // Leaving this blank means "sell at the plan price". The service
            // also enforces that a custom price cannot undercut the plan.
            'retail_price_naira' => ['nullable', 'numeric', 'decimal:0,2', 'min:1'],
            'pin_format' => ['nullable', Rule::enum(VoucherPinFormat::class)],
            'dashed_pin' => ['nullable', 'boolean'],
        ]);

        $plan = $organization->accessPlans()->where('access_type', 'paid')->findOrFail($data['access_plan_id']);
        $retailPriceKobo = isset($data['retail_price_naira'])
            ? (int) round($data['retail_price_naira'] * 100)
            : null;

        if ($retailPriceKobo !== null && $retailPriceKobo < $plan->price_kobo) {
            throw ValidationException::withMessages([
                'retail_price_naira' => 'The voucher price cannot be below the selected plan price.',
            ]);
        }

        $batch = $service->createBatch(
            $organization,
            $plan,
            $data['quantity'],
            $retailPriceKobo,
            VoucherPinFormat::tryFrom($data['pin_format'] ?? '') ?? VoucherPinFormat::Numbers,
            $request->has('dashed_pin') ? $request->boolean('dashed_pin') : true,
        );

        // Redirecting straight at the PDF leaves the browser downloading a file
        // instead of navigating, so the page never reloads and the submit
        // spinner never clears. Land back on the list and let the view pull the
        // download in out of band.
        return redirect()->route('vouchers.index')
            ->with('success', 'Voucher batch generated.')
            ->with('download_batch', $batch->getRouteKey());
    }

    public function print(Request $request, VoucherBatch $batch): Response
    {
        abort_unless($batch->organization_id === $request->attributes->get('organization')->id, 404);
        $batch->load('organization', 'accessPlan', 'vouchers');
        $batch->update(['status' => 'printed', 'printed_at' => now()]);
        $batch->vouchers()->where('status', 'generated')->update(['status' => 'printed']);

        return Pdf::loadView('operator.voucher-pdf', ['batch' => $batch])
            ->setPaper('a4')
            ->download($batch->reference.'.pdf');
    }
}
