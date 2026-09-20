<?php

namespace App\Http\Controllers\Api;

use App\Domain\Enums\PlanValidityMode;
use App\Http\Controllers\Controller;
use App\Models\AccessPlan;
use App\Models\Organization;
use App\Services\Access\AccessPlanManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class MobileAccessPlanController extends Controller
{
    private const TYPES = ['paid', 'free', 'internal'];

    public function index(Request $request, Organization $organization, AccessPlanManager $manager): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'type' => ['nullable', Rule::in(self::TYPES)],
            'state' => ['nullable', Rule::in(['active', 'inactive'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $plans = $organization->accessPlans()
            ->withCount(['voucherBatches', 'transactions', 'accessCredentials', 'sessions'])
            ->when(trim((string) ($data['search'] ?? '')), fn ($query, $term) => $query->where('name', 'like', '%'.$term.'%'))
            ->when($data['type'] ?? null, fn ($query, $type) => $query->where('access_type', $type))
            ->when($data['state'] ?? null, fn ($query, $state) => $query->where('is_active', $state === 'active'))
            ->latest()
            ->paginate((int) ($data['per_page'] ?? 20));
        $canManage = $this->canManage($request, $organization);

        return response()->json([
            'data' => [
                'plans' => collect($plans->items())
                    ->map(fn (AccessPlan $plan) => $this->plan($plan, $this->listedPlanHasUsage($plan), $canManage))
                    ->values(),
                'pagination' => [
                    'current_page' => $plans->currentPage(),
                    'last_page' => $plans->lastPage(),
                    'per_page' => $plans->perPage(),
                    'total' => $plans->total(),
                ],
                'options' => [
                    'types' => collect(self::TYPES)->map(fn (string $type) => [
                        'value' => $type,
                        'label' => ucfirst($type),
                    ])->values(),
                    'validity_modes' => collect(PlanValidityMode::cases())->map(fn (PlanValidityMode $mode) => [
                        'value' => $mode->value,
                        'label' => $mode->label(),
                    ])->values(),
                    'timezone' => $organization->timezone,
                ],
                'permissions' => ['can_manage' => $canManage],
            ],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function store(
        Request $request,
        Organization $organization,
        AccessPlanManager $manager,
    ): JsonResponse {
        abort_unless($this->canManage($request, $organization), 403, 'You cannot create plans for this organization.');
        $attributes = $this->attributes($this->validated($request, $organization));
        $plan = $manager->create($organization, $attributes);

        return response()->json([
            'data' => $this->plan($plan, false, true),
            'message' => 'Access plan created.',
        ], Response::HTTP_CREATED)->header('Cache-Control', 'no-store, private');
    }

    public function update(
        Request $request,
        Organization $organization,
        AccessPlan $plan,
        AccessPlanManager $manager,
    ): JsonResponse {
        abort_unless($this->canManage($request, $organization), 403, 'You cannot edit plans for this organization.');
        abort_unless($plan->organization_id === $organization->id, 404);
        $data = $this->validated($request, $organization, $plan);
        $updated = $manager->update(
            $organization,
            $plan,
            $this->attributes($data) + ['is_active' => (bool) $data['is_active']],
        );
        $used = $manager->hasUsage($updated);

        return response()->json([
            'data' => $this->plan($updated, $used, true),
            'message' => 'Access plan updated.',
        ])->header('Cache-Control', 'no-store, private');
    }

    public function destroy(
        Request $request,
        Organization $organization,
        AccessPlan $plan,
        AccessPlanManager $manager,
    ): Response {
        abort_unless($this->canManage($request, $organization), 403, 'You cannot delete plans for this organization.');
        $manager->delete($organization, $plan);

        return response()->noContent();
    }

    private function validated(Request $request, Organization $organization, ?AccessPlan $plan = null): array
    {
        $name = Rule::unique('access_plans', 'name')
            ->where(fn ($query) => $query->where('organization_id', $organization->id));
        if ($plan) {
            $name->ignore($plan->id);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255', $name],
            'access_type' => ['required', Rule::in(self::TYPES)],
            'price_kobo' => [
                'required',
                'integer',
                Rule::when($request->input('access_type') === 'paid', ['min:100'], ['min:0']),
            ],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'data_limit_mb' => ['nullable', 'integer', 'min:1'],
            'download_kbps' => ['nullable', 'integer', 'min:64'],
            'upload_kbps' => ['nullable', 'integer', 'min:64'],
            'simultaneous_use' => ['required', 'integer', 'min:1', 'max:20'],
            'validity_days' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'validity_mode' => ['nullable', Rule::enum(PlanValidityMode::class)],
            'is_active' => [$plan ? 'required' : 'sometimes', 'boolean'],
        ]);
    }

    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'access_type' => $data['access_type'],
            'price_kobo' => (int) $data['price_kobo'],
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'data_limit_bytes' => isset($data['data_limit_mb']) ? $data['data_limit_mb'] * 1024 * 1024 : null,
            'download_kbps' => $data['download_kbps'] ?? null,
            'upload_kbps' => $data['upload_kbps'] ?? null,
            'simultaneous_use' => (int) $data['simultaneous_use'],
            'validity_days' => $data['validity_days'] ?? null,
            'validity_mode' => $data['validity_mode'] ?? PlanValidityMode::Midnight,
        ];
    }

    private function plan(AccessPlan $plan, bool $used, bool $canManage): array
    {
        return [
            'id' => $plan->uuid,
            'name' => $plan->name,
            'access_type' => $plan->access_type,
            'price_kobo' => (int) $plan->price_kobo,
            'duration_minutes' => $plan->duration_minutes,
            'data_limit_mb' => $plan->data_limit_bytes ? intdiv($plan->data_limit_bytes, 1024 * 1024) : null,
            'data_allowance' => $plan->dataAllowance(),
            'download_kbps' => $plan->download_kbps,
            'upload_kbps' => $plan->upload_kbps,
            'simultaneous_use' => (int) $plan->simultaneous_use,
            'validity_days' => $plan->validity_days,
            'validity_mode' => $plan->validity_mode->value,
            'validity_label' => $plan->validityLabel(),
            'starts_on_first_use' => (bool) $plan->starts_on_first_use,
            'is_active' => (bool) $plan->is_active,
            'is_used' => $used,
            'can_edit' => $canManage,
            'can_delete' => $canManage && ! $used,
            'created_at' => $plan->created_at?->toIso8601String(),
        ];
    }

    private function canManage(Request $request, Organization $organization): bool
    {
        return $request->user()->is_platform_admin
            || in_array($request->user()->roleFor($organization), ['owner', 'manager'], true);
    }

    private function listedPlanHasUsage(AccessPlan $plan): bool
    {
        return (int) $plan->voucher_batches_count
            + (int) $plan->transactions_count
            + (int) $plan->access_credentials_count
            + (int) $plan->sessions_count > 0;
    }
}
