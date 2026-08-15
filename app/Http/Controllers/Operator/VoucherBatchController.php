<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\VoucherBatch;
use App\Services\Vouchers\VoucherService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class VoucherBatchController extends Controller
{
    public function index(Organization $organization): View
    {
        return view('operator.vouchers', [
            'batches' => $organization->voucherBatches()->with('accessPlan')->latest()->paginate(20),
            'plans' => $organization->accessPlans()->where('is_active', true)->where('access_type', 'paid')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Organization $organization, VoucherService $service): RedirectResponse
    {
        $data = $request->validate([
            'access_plan_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:5000'],
            'retail_price_naira' => ['nullable', 'numeric', 'min:0'],
        ]);

        $plan = $organization->accessPlans()->where('access_type', 'paid')->findOrFail($data['access_plan_id']);
        $batch = $service->createBatch(
            $organization,
            $plan,
            $data['quantity'],
            isset($data['retail_price_naira']) ? (int) round($data['retail_price_naira'] * 100) : null,
        );

        return redirect()->route('vouchers.print', $batch)->with('success', 'Voucher batch generated.');
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