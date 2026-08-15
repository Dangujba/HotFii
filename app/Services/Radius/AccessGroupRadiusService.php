<?php

namespace App\Services\Radius;

use App\Models\AccessGroup;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class AccessGroupRadiusService
{
    public function synchronize(Customer $customer, AccessGroup $group): void
    {
        foreach ($customer->credentials()->where('status', 'active')->get() as $credential) {
            DB::table('radcheck')->updateOrInsert(
                ['username' => $credential->username, 'attribute' => 'Simultaneous-Use'],
                ['op' => ':=', 'value' => (string) $group->device_limit],
            );

            if ($loginTime = $this->loginTime($group->schedule ?? [])) {
                DB::table('radcheck')->updateOrInsert(
                    ['username' => $credential->username, 'attribute' => 'Login-Time'],
                    ['op' => ':=', 'value' => $loginTime],
                );
            }

            if ($group->download_kbps || $group->upload_kbps) {
                $upload = $group->upload_kbps ?: $group->download_kbps;
                $download = $group->download_kbps ?: $group->upload_kbps;
                DB::table('radreply')->updateOrInsert(
                    ['username' => $credential->username, 'attribute' => 'Mikrotik-Rate-Limit'],
                    ['op' => ':=', 'value' => "{$upload}k/{$download}k"],
                );
            }

            if ($group->data_limit_bytes) {
                DB::table('radreply')->updateOrInsert(
                    ['username' => $credential->username, 'attribute' => 'Mikrotik-Total-Limit'],
                    ['op' => ':=', 'value' => (string) ($group->data_limit_bytes % 4294967296)],
                );
            }
        }
    }

    private function loginTime(array $schedule): ?string
    {
        $days = array_values(array_intersect(
            ['Mo','Tu','We','Th','Fr','Sa','Su'],
            $schedule['days'] ?? [],
        ));

        if (! $days || empty($schedule['start']) || empty($schedule['end'])) {
            return null;
        }

        return implode('', $days).str_replace(':', '', $schedule['start']).'-'.str_replace(':', '', $schedule['end']);
    }
}