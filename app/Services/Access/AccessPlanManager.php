<?php

namespace App\Services\Access;

use App\Models\AccessPlan;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccessPlanManager
{
    private const LOCKED_AFTER_USE = [
        'access_type',
        'duration_minutes',
        'data_limit_bytes',
        'download_kbps',
        'upload_kbps',
        'simultaneous_use',
        'validity_days',
        'validity_mode',
    ];

    public function create(Organization $organization, array $attributes): AccessPlan
    {
        return $organization->accessPlans()->create($attributes);
    }

    public function update(Organization $organization, AccessPlan $plan, array $attributes): AccessPlan
    {
        $this->guard($organization, $plan);

        return DB::transaction(function () use ($organization, $plan, $attributes): AccessPlan {
            $plan = $organization->accessPlans()->whereKey($plan->id)->lockForUpdate()->firstOrFail();

            if ($this->hasUsage($plan)) {
                foreach (self::LOCKED_AFTER_USE as $field) {
                    $current = $plan->getRawOriginal($field);
                    $next = $attributes[$field] instanceof \BackedEnum
                        ? $attributes[$field]->value
                        : $attributes[$field];

                    if ((string) ($current ?? '') !== (string) ($next ?? '')) {
                        throw ValidationException::withMessages([
                            'plan' => 'This plan has already been issued or sold. Its type and access limits are locked to protect existing customers. You can still change its name, future price, or active status.',
                        ]);
                    }
                }
            }

            $plan->update($attributes);

            return $plan->refresh();
        });
    }

    public function delete(Organization $organization, AccessPlan $plan): void
    {
        $this->guard($organization, $plan);

        DB::transaction(function () use ($organization, $plan): void {
            $plan = $organization->accessPlans()->whereKey($plan->id)->lockForUpdate()->firstOrFail();

            if ($this->hasUsage($plan)) {
                throw ValidationException::withMessages([
                    'plan' => 'This plan is already linked to vouchers, sales, or access records and cannot be deleted. Edit it and switch off Active instead.',
                ]);
            }

            $plan->delete();
        });
    }

    public function hasUsage(AccessPlan $plan): bool
    {
        return $plan->voucherBatches()->exists()
            || $plan->transactions()->exists()
            || $plan->accessCredentials()->exists()
            || $plan->sessions()->exists();
    }

    private function guard(Organization $organization, AccessPlan $plan): void
    {
        abort_unless($plan->organization_id === $organization->id, 404);
    }
}
