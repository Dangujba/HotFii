<?php

namespace App\Http\Controllers\Operator;

use App\Domain\Enums\VoucherPinFormat;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\NetworkDevice;
use App\Models\VoucherBatch;
use App\Services\Vouchers\VoucherPinGenerator;
use App\Services\Vouchers\VoucherService;
use App\Support\ListFilters;
use App\Support\OrganizationRouterFilter;
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

    /**
     * Large voucher batches are intentionally split into small PDFs.
     * 100 vouchers = 5 A4 pages at 20 vouchers per page.
     */
    private const PDF_CHUNK_SIZE = 100;

    public function index(Request $request, Organization $organization): View
    {
        [$routers, $routerId] = OrganizationRouterFilter::resolve($request, $organization);

        $filters = [
            'search' => ListFilters::text($request, 'search'),
            'status' => ListFilters::choice($request, 'status', self::BATCH_STATUSES),
            'plan' => ListFilters::id($request, 'plan'),
            'router' => $routerId,
        ];

        return view('operator.vouchers', [
            'batches' => $organization->voucherBatches()
                ->with('accessPlan', 'networkDevice')
                ->when($filters['search'], fn ($query, $term) => $query->where('reference', 'like', "%{$term}%"))
                ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
                ->when($filters['plan'], fn ($query, $plan) => $query->where('access_plan_id', $plan))
                ->when($filters['router'], fn ($query, $router) => $query->where('network_device_id', $router))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'routers' => $routers,
            'plans' => $organization->accessPlans()->where('is_active', true)->where('access_type', 'paid')->orderBy('name')->get(),
            // Batches outlive the plans they were minted from, so the filter
            // list is not the same as the list you can generate against.
            'filterPlans' => $organization->accessPlans()->orderBy('name')->get(['id', 'name']),
            'pinFormats' => VoucherPinFormat::cases(),
            'pinLengths' => VoucherPinGenerator::LENGTHS,
            'statuses' => self::BATCH_STATUSES,
            'filters' => $filters,
            'filtered' => ListFilters::any($filters),
        ]);
    }

    public function store(Request $request, Organization $organization, VoucherService $service): RedirectResponse
    {
        $data = $request->validate([
            'network_device_id' => [
                'required',
                function (
                    string $attribute,
                    mixed $value,
                    \Closure $fail
                ) use ($organization) {
                    if ($value === 'all') {
                        return;
                    }

                    if (
                        ! ctype_digit((string) $value)
                        || ! NetworkDevice::query()
                            ->where(
                                'organization_id',
                                $organization->id
                            )
                            ->whereKey((int) $value)
                            ->exists()
                    ) {
                        $fail(
                            'Choose a valid router or All routers.'
                        );
                    }
                },
            ],
            'access_plan_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:5000'],
            // Leaving this blank means "sell at the plan price". The service
            // also enforces that a custom price cannot undercut the plan.
            'retail_price_naira' => ['nullable', 'numeric', 'decimal:0,2', 'min:1'],
            'pin_format' => ['nullable', Rule::enum(VoucherPinFormat::class)],
            'pin_length' => ['nullable', 'integer', Rule::in(VoucherPinGenerator::LENGTHS)],
            'dashed_pin' => ['nullable', 'boolean'],
        ]);

        $device = $data['network_device_id'] === 'all'
            ? null
            : NetworkDevice::query()
                ->where('organization_id', $organization->id)
                ->findOrFail((int) $data['network_device_id']);

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
            (int) ($data['pin_length'] ?? 12),
            $device,
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
        abort_unless(
            $batch->organization_id ===
            $request->attributes->get('organization')->id,
            404
        );

        $totalParts = max(
            1,
            (int) ceil(
                $batch->quantity / self::PDF_CHUNK_SIZE
            )
        );

        $part = max(
            1,
            $request->integer('part', 1)
        );

        abort_if($part > $totalParts, 404);

        $batch->load(
            'organization',
            'accessPlan',
            'networkDevice'
        );

        $vouchers = $batch->vouchers()
            ->orderBy('id')
            ->forPage(
                $part,
                self::PDF_CHUNK_SIZE
            )
            ->get();

        abort_if($vouchers->isEmpty(), 404);

        /*
         * The Blade template already reads $batch->vouchers.
         * Replace that relation with only this PDF's 100-voucher slice.
         */
        $batch->setRelation(
            'vouchers',
            $vouchers
        );

        $filename =
            $totalParts === 1
                ? $batch->reference . '.pdf'
                : sprintf(
                    '%s-part-%02d-of-%02d.pdf',
                    $batch->reference,
                    $part,
                    $totalParts
                );

        /*
         * Generate the response FIRST.
         *
         * If DomPDF throws or times out, no voucher is falsely marked printed.
         */
        $response = Pdf::loadView(
            'operator.voucher-pdf',
            [
                'batch' => $batch,
                'printPart' => $part,
                'printParts' => $totalParts,
            ]
        )
            ->setPaper('a4', 'landscape')
            ->download($filename);

        /*
         * Only vouchers contained in the successfully generated PDF are
         * considered printed.
         */
        $batch->vouchers()
            ->whereIn(
                'id',
                $vouchers->pluck('id')
            )
            ->where(
                'status',
                'generated'
            )
            ->update([
                'status' => 'printed',
            ]);

        /*
         * The whole batch becomes printed only after every voucher has
         * actually been included in a successfully generated PDF.
         */
        if (
            ! $batch->vouchers()
                ->where(
                    'status',
                    'generated'
                )
                ->exists()
        ) {
            $batch->update([
                'status' => 'printed',
                'printed_at' =>
                    $batch->printed_at
                    ?? now(),
            ]);
        }

        return $response;
    }
}
