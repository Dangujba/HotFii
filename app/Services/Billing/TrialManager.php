<?php

namespace App\Services\Billing;

use App\Domain\Enums\OrganizationStatus;
use App\Models\Organization;

class TrialManager
{
    public function start(Organization $organization): Organization
    {
        if ($organization->trial_started_at) return $organization;

        $organization->forceFill([
            'status' => OrganizationStatus::Trial,
            'trial_started_at' => now(),
            'trial_ends_at' => now()->addDays((int) config('hotfii.commerce.trial_days')),
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