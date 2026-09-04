<?php

namespace App\Http\Controllers\Operator;

use App\Domain\Enums\VoucherPinFormat;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\VoucherBatch;
use App\Services\Vouchers\VoucherService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VoucherBatchController extends Controller
{
    public function index(Organization $organization): View
    {
        return view('operator.vouchers', [
            'batches' => $organization->voucherBatches()->with('accessPlan')->latest()->paginate(20),
            'plans' => $organization->accessPlans()->where('is_active', true)->where('access_type', 'paid')->orderBy('name')->get(),
            'pinFormats' => VoucherPinFormat::cases(),
        ]);
    }

    public function store(Request $request, Organization $organization, VoucherService $service): RedirectResponse
    {
        $data = $request->validate([
            'access_plan_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:5000'],
            'retail_price_naira' => ['nullable', 'numeric', 'min:0'],
            'pin_format' => ['nullable', Rule::enum(VoucherPinFormat::class)],
            'dashed_pin' => ['nullable', 'boolean'],
        ]);

        $plan = $organization->accessPlans()->where('access_type', 'paid')->findOrFail($data['access_plan_id']);
        $batch = $service->createBatch(
            $organization,
            $plan,
            $data['quantity'],
            isset($data['retail_price_naira']) ? (int) round($data['retail_price_naira'] * 100) : null,
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