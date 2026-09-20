<?php

namespace App\Http\Controllers\Api;

use App\Domain\Enums\VoucherPinFormat;
use App\Domain\Enums\VoucherStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use App\Services\Vouchers\VoucherPinGenerator;
use App\Services\Vouchers\VoucherService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MobileVoucherBatchController extends Controller
{
    private const BATCH_STATUSES = ['generated', 'printed'];

    private const PDF_CHUNK_SIZE = 100;

    public function index(Request $request, Organization $organization): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::in(self::BATCH_STATUSES)],
            'plan' => ['nullable', 'uuid'],
            'router' => ['nullable', 'uuid'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $plan = isset($data['plan'])
            ? $organization->accessPlans()->where('uuid', $data['plan'])->firstOrFail()
            : null;
        $router = isset($data['router'])
            ? $organization->networkDevices()->where('uuid', $data['router'])->firstOrFail()
            : null;

        $batches = $organization->voucherBatches()
            ->with('accessPlan', 'networkDevice')
            ->withCount([
                'vouchers as locked_vouchers_count' => fn ($query) => $query->where('status', '!=', VoucherStatus::Generated->value),
                'vouchers as used_vouchers_count' => fn ($query) => $this->usedVoucherQuery($query),
                'vouchers as available_vouchers_count' => fn ($query) => $query->whereIn('status', [
                    'generated', 'printed', 'assigned', 'sold',
                ]),
                'vouchers as active_vouchers_count' => fn ($query) => $query->where('status', 'active'),
                'vouchers as expired_vouchers_count' => fn ($query) => $query->where('status', 'expired'),
                'vouchers as revoked_vouchers_count' => fn ($query) => $query->where('status', 'revoked'),
            ])
            ->when(trim((string) ($data['search'] ?? '')), fn ($query, $term) => $query->where('reference', 'like', '%'.$term.'%'))
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($plan, fn ($query) => $query->where('access_plan_id', $plan->id))
            ->when($router, fn ($query) => $query->where('network_device_id', $router->id))
            ->latest()
            ->paginate((int) ($data['per_page'] ?? 20));

        return response()->json([
            'data' => [
                'batches' => collect($batches->items())->map(fn (VoucherBatch $batch) => $this->batch($batch))->values(),
                'pagination' => [
                    'current_page' => $batches->currentPage(),
                    'last_page' => $batches->lastPage(),
                    'per_page' => $batches->perPage(),
                    'total' => $batches->total(),
                ],
                'options' => $this->options($organization),
                'permissions' => [
                    'can_create' => $this->canCreate($request, $organization),
                    'can_manage' => $this->canManage($request, $organization),
                ],
            ],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function show(Request $request, Organization $organization, VoucherBatch $batch): JsonResponse
    {
        $this->guardBatch($organization, $batch);

        return response()->json([
            'data' => $this->detail(
                $batch->load('accessPlan', 'networkDevice', 'vouchers'),
                $this->canManage($request, $organization),
            ),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, Organization $organization, VoucherService $service): JsonResponse
    {
        abort_unless($this->canCreate($request, $organization), 403, 'You cannot generate vouchers for this organization.');
        $data = $request->validate([
            'network_device_id' => ['required', 'string'],
            'access_plan_id' => ['required', 'uuid'],
            'quantity' => ['required', 'integer', 'min:1', 'max:5000'],
            'retail_price_kobo' => ['nullable', 'integer', 'min:100'],
            'pin_format' => ['nullable', Rule::enum(VoucherPinFormat::class)],
            'pin_length' => ['nullable', 'integer', Rule::in(VoucherPinGenerator::LENGTHS)],
            'dashed_pin' => ['nullable', 'boolean'],
        ]);
        $plan = $organization->accessPlans()
            ->where('access_type', 'paid')
            ->where('is_active', true)
            ->where('uuid', $data['access_plan_id'])
            ->firstOrFail();
        $device = $this->device($organization, $data['network_device_id']);
        $price = $data['retail_price_kobo'] ?? null;

        if ($price !== null && $price < $plan->price_kobo) {
            throw ValidationException::withMessages([
                'retail_price_kobo' => 'The voucher price cannot be below the selected plan price.',
            ]);
        }

        $batch = $service->createBatch(
            $organization,
            $plan,
            (int) $data['quantity'],
            $price,
            VoucherPinFormat::from($data['pin_format'] ?? 'numbers'),
            (bool) ($data['dashed_pin'] ?? true),
            (int) ($data['pin_length'] ?? 12),
            $device,
        );

        return response()->json([
            'data' => $this->detail($batch->load('networkDevice'), $this->canManage($request, $organization)),
            'message' => 'Voucher batch generated.',
        ], Response::HTTP_CREATED)->header('Cache-Control', 'no-store, private');
    }

    public function update(Request $request, Organization $organization, VoucherBatch $batch): JsonResponse
    {
        $this->guardBatch($organization, $batch);
        abort_unless($this->canManage($request, $organization), 403, 'You cannot edit voucher batches for this organization.');
        $data = $request->validate([
            'network_device_id' => ['required', 'string'],
            'access_plan_id' => ['required', 'uuid'],
            'retail_price_kobo' => ['required', 'integer', 'min:100'],
        ]);
        $device = $this->device($organization, $data['network_device_id']);
        $plan = $organization->accessPlans()
            ->where('access_type', 'paid')
            ->where('uuid', $data['access_plan_id'])
            ->firstOrFail();
        $keepsGrandfatheredPrice = $batch->access_plan_id === $plan->id
            && $batch->retail_price_kobo === (int) $data['retail_price_kobo'];

        if ((int) $data['retail_price_kobo'] < $plan->price_kobo && ! $keepsGrandfatheredPrice) {
            throw ValidationException::withMessages([
                'retail_price_kobo' => 'The voucher price cannot be below the selected plan price.',
            ]);
        }

        DB::transaction(function () use ($organization, $batch, $plan, $device, $data): void {
            $locked = $organization->voucherBatches()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($locked->vouchers()->where('status', '!=', 'generated')->exists()) {
                throw ValidationException::withMessages([
                    'batch' => 'This batch has already been shared, printed, or used. Its plan, coverage, and price can no longer be edited.',
                ]);
            }
            $locked->update([
                'access_plan_id' => $plan->id,
                'network_device_id' => $device?->id,
                'retail_price_kobo' => (int) $data['retail_price_kobo'],
            ]);
            $locked->vouchers()->update([
                'network_device_id' => $device?->id,
                'price_snapshot_kobo' => (int) $data['retail_price_kobo'],
            ]);
        });

        return response()->json([
            'data' => $this->detail($batch->refresh()->load('accessPlan', 'networkDevice', 'vouchers'), true),
            'message' => 'Voucher batch updated.',
        ])->header('Cache-Control', 'no-store, private');
    }

    public function destroy(Request $request, Organization $organization, VoucherBatch $batch): Response
    {
        $this->guardBatch($organization, $batch);
        abort_unless($this->canManage($request, $organization), 403, 'You cannot delete voucher batches for this organization.');
        DB::transaction(function () use ($organization, $batch): void {
            $locked = $organization->voucherBatches()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($this->usedVoucherQuery($locked->vouchers())->exists()) {
                throw ValidationException::withMessages([
                    'batch' => 'This batch contains sold or activated voucher history and cannot be deleted.',
                ]);
            }
            $locked->delete();
        });

        return response()->noContent();
    }

    public function pdf(Request $request, Organization $organization, VoucherBatch $batch): Response
    {
        $this->guardBatch($organization, $batch);
        abort_unless($this->canCreate($request, $organization), 403, 'You cannot export vouchers for this organization.');

        $totalParts = max(1, (int) ceil($batch->quantity / self::PDF_CHUNK_SIZE));
        $part = max(1, $request->integer('part', 1));
        abort_if($part > $totalParts, 404);

        $batch->load('organization', 'accessPlan', 'networkDevice');
        $vouchers = $batch->vouchers()
            ->orderBy('id')
            ->forPage($part, self::PDF_CHUNK_SIZE)
            ->get();
        abort_if($vouchers->isEmpty(), 404);

        $batch->setRelation('vouchers', $vouchers);
        $filename = $totalParts === 1
            ? $batch->reference.'.pdf'
            : sprintf('%s-part-%02d-of-%02d.pdf', $batch->reference, $part, $totalParts);

        $response = Pdf::loadView('operator.voucher-pdf', [
            'batch' => $batch,
            'printPart' => $part,
            'printParts' => $totalParts,
        ])->setPaper('a4', 'landscape')->download($filename);

        $batch->vouchers()
            ->whereIn('id', $vouchers->pluck('id'))
            ->where('status', VoucherStatus::Generated->value)
            ->update(['status' => VoucherStatus::Printed->value]);

        if (! $batch->vouchers()->where('status', VoucherStatus::Generated->value)->exists()) {
            $batch->update([
                'status' => VoucherStatus::Printed->value,
                'printed_at' => $batch->printed_at ?? now(),
            ]);
        }

        return $response->header('Cache-Control', 'no-store, private');
    }

    public function share(Request $request, Organization $organization, VoucherBatch $batch): JsonResponse
    {
        $this->guardBatch($organization, $batch);
        abort_unless($this->canCreate($request, $organization), 403, 'You cannot share vouchers for this organization.');
        $batch->load('organization', 'accessPlan', 'networkDevice');
        $codes = DB::transaction(function () use ($organization, $batch) {
            $locked = $organization->voucherBatches()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $vouchers = $locked->vouchers()->orderBy('id')->get();
            $locked->vouchers()->where('status', 'generated')->update(['status' => 'printed']);
            $locked->update([
                'status' => 'printed',
                'printed_at' => $locked->printed_at ?? now(),
            ]);

            return $vouchers->map(fn (Voucher $voucher) => [
                'id' => $voucher->uuid,
                'serial_number' => $voucher->serial_number,
                'code' => $voucher->code_cipher,
            ])->values();
        });

        return response()->json([
            'data' => [
                'reference' => $batch->reference,
                'organization_name' => $batch->organization->branding['portal_name']
                    ?? $batch->organization->name,
                'plan_name' => $batch->accessPlan->name,
                'access' => collect([
                    $batch->accessPlan->duration_minutes
                        ? number_format($batch->accessPlan->duration_minutes).' min'
                        : null,
                    $batch->accessPlan->dataAllowance(),
                ])->filter()->implode(' · ') ?: 'Unlimited',
                'validity' => $batch->accessPlan->validityLabel(),
                'coverage' => $batch->networkDevice?->name ?? 'All routers',
                'price_kobo' => (int) $batch->retail_price_kobo,
                'codes' => $codes,
            ],
        ])->header('Cache-Control', 'no-store, private');
    }

    private function device(Organization $organization, string $id)
    {
        if ($id === 'all') {
            return null;
        }
        if (! preg_match('/^[0-9a-f-]{36}$/i', $id)) {
            throw ValidationException::withMessages(['network_device_id' => 'Choose a valid router or All routers.']);
        }

        return $organization->networkDevices()->where('uuid', $id)->firstOrFail();
    }

    private function options(Organization $organization): array
    {
        return [
            'routers' => $organization->networkDevices()->orderBy('name')->get()->map(fn ($router) => [
                'id' => $router->uuid,
                'name' => $router->name,
                'nas_identifier' => $router->nas_identifier,
                'status' => $router->status->value,
            ])->values(),
            'plans' => $organization->accessPlans()->where('access_type', 'paid')->orderBy('name')->get()->map(fn ($plan) => [
                'id' => $plan->uuid,
                'name' => $plan->name,
                'price_kobo' => (int) $plan->price_kobo,
                'is_active' => (bool) $plan->is_active,
                'validity' => $plan->validityLabel(),
            ])->values(),
            'pin_lengths' => VoucherPinGenerator::LENGTHS,
            'pin_formats' => collect(VoucherPinFormat::cases())->map(fn ($format) => [
                'value' => $format->value,
                'label' => $format->label(),
            ])->values(),
            'statuses' => self::BATCH_STATUSES,
        ];
    }

    private function batch(VoucherBatch $batch): array
    {
        $locked = (int) ($batch->locked_vouchers_count
            ?? $batch->vouchers()->where('status', '!=', 'generated')->count());
        $used = (int) ($batch->used_vouchers_count
            ?? $this->usedVoucherQuery($batch->vouchers())->count());

        return [
            'id' => $batch->uuid,
            'reference' => $batch->reference,
            'quantity' => (int) $batch->quantity,
            'pin_length' => (int) $batch->pin_length,
            'retail_price_kobo' => (int) $batch->retail_price_kobo,
            'retail_value_kobo' => (int) $batch->retail_price_kobo * (int) $batch->quantity,
            'status' => $batch->status instanceof \BackedEnum ? $batch->status->value : $batch->status,
            'plan' => ['id' => $batch->accessPlan->uuid, 'name' => $batch->accessPlan->name],
            'router' => $batch->networkDevice
                ? ['id' => $batch->networkDevice->uuid, 'name' => $batch->networkDevice->name]
                : null,
            'counts' => [
                'available' => (int) ($batch->available_vouchers_count ?? 0),
                'active' => (int) ($batch->active_vouchers_count ?? 0),
                'expired' => (int) ($batch->expired_vouchers_count ?? 0),
                'revoked' => (int) ($batch->revoked_vouchers_count ?? 0),
            ],
            'can_edit' => $locked === 0,
            'can_delete' => $used === 0,
            'created_at' => $batch->created_at->toIso8601String(),
            'printed_at' => $batch->printed_at?->toIso8601String(),
        ];
    }

    private function detail(VoucherBatch $batch, bool $canManage): array
    {
        $batch->loadMissing('accessPlan', 'networkDevice', 'vouchers');
        $summary = $this->batch($batch);
        $counts = $batch->vouchers->countBy(fn (Voucher $voucher) => $voucher->status->value);
        $summary['counts'] = [
            'available' => collect(['generated', 'printed', 'assigned', 'sold'])->sum(fn ($status) => $counts->get($status, 0)),
            'active' => $counts->get('active', 0),
            'expired' => $counts->get('expired', 0),
            'revoked' => $counts->get('revoked', 0),
        ];
        $summary['can_edit'] = $canManage
            && $batch->vouchers->every(fn (Voucher $voucher) => $voucher->status === VoucherStatus::Generated);
        $summary['can_delete'] = $canManage
            && ! $batch->vouchers->contains(fn (Voucher $voucher) => $this->voucherWasUsed($voucher));
        $summary['vouchers'] = $batch->vouchers->sortBy('id')->values()->map(fn (Voucher $voucher) => [
            'id' => $voucher->uuid,
            'serial_number' => $voucher->serial_number,
            'code_last_four' => $voucher->code_last_four,
            'status' => $voucher->status->value,
            'sold_at' => $voucher->sold_at?->toIso8601String(),
            'activated_at' => $voucher->activated_at?->toIso8601String(),
            'expires_at' => $voucher->expires_at?->toIso8601String(),
        ]);

        return $summary;
    }

    private function usedVoucherQuery($query)
    {
        return $query->where(function ($query) {
            $query->whereNotNull('sold_at')
                ->orWhereNotNull('activated_at')
                ->orWhereIn('status', ['sold', 'active', 'expired', 'revoked']);
        });
    }

    private function voucherWasUsed(Voucher $voucher): bool
    {
        return $voucher->sold_at !== null
            || $voucher->activated_at !== null
            || in_array($voucher->status, [
                VoucherStatus::Sold,
                VoucherStatus::Active,
                VoucherStatus::Expired,
                VoucherStatus::Revoked,
            ], true);
    }

    private function canCreate(Request $request, Organization $organization): bool
    {
        return $request->user()->is_platform_admin
            || in_array($request->user()->roleFor($organization), ['owner', 'manager', 'agent'], true);
    }

    private function canManage(Request $request, Organization $organization): bool
    {
        return $request->user()->is_platform_admin
            || in_array($request->user()->roleFor($organization), ['owner', 'manager'], true);
    }

    private function guardBatch(Organization $organization, VoucherBatch $batch): void
    {
        abort_unless($batch->organization_id === $organization->id, 404);
    }
}
