<?php

namespace App\Services\Access;

use App\Models\AccessCredential;

class AllowanceService
{
    public function forCredential(?AccessCredential $credential): ?array
    {
        if (! $credential || ! $credential->accessPlan) {
            return null;
        }

        $sessions = $credential->organization->sessions()
            ->where('radius_username', $credential->username)
            ->get();

        $usedBytes = $sessions->sum(fn ($session) => $session->input_bytes + $session->output_bytes);
        $usedSeconds = $sessions->sum(function ($session) {
            if (! $session->started_at) return 0;
            return $session->started_at->diffInSeconds($session->stopped_at ?? now());
        });

        $plan = $credential->accessPlan;
        $limitSeconds = $plan->duration_minutes ? $plan->duration_minutes * 60 : null;

        return [
            'used_bytes' => $usedBytes,
            'remaining_bytes' => $plan->data_limit_bytes !== null ? max(0, $plan->data_limit_bytes - $usedBytes) : null,
            'used_seconds' => $usedSeconds,
            'remaining_seconds' => $limitSeconds !== null ? max(0, $limitSeconds - $usedSeconds) : null,
            'expires_at' => $credential->expires_at,
        ];
    }
}