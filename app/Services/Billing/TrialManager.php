<?php

namespace App\Services\Billing;

use App\Domain\Enums\OrganizationStatus;
use App\Models\Organization;
use Carbon\Carbon;

class TrialManager
{
    public function start(Organization $organization, ?\DateTimeInterface $at = null): Organization
    {
        if ($organization->trial_started_at) return $organization;

        $startedAt = $at ? Carbon::parse($at) : now();

        $organization->forceFill([
            'status' => OrganizationStatus::Trial,
            'trial_started_at' => $startedAt,
            'trial_ends_at' => $startedAt->copy()->addDays((int) config('hotfii.commerce.trial_days')),
            'trial_sales_kobo' => 0,
        ])->save();

        return $organization->refresh();
    }

    public function hasEnded(Organization $organization): bool
    {
        return ($organization->trial_ends_at?->isPast() ?? false)
            || $organization->trial_sales_kobo >= (int) config('hotfii.commerce.trial_sales_cap_kobo');
    }
}
