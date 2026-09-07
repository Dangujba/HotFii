<?php

namespace App\Services\Radius;

use App\Models\AccessCredential;
use App\Models\AccessPlan;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RadiusCredentialService
{
    public function issue(
        Organization $organization,
        AccessPlan $plan,
        ?Customer $customer = null,
        ?Voucher $voucher = null,
        ?string $username = null,
        ?string $password = null,
    ): AccessCredential {
        return DB::transaction(function () use ($organization, $plan, $customer, $voucher, $username, $password) {
            $username ??= 'hf-'.Str::lower(Str::random(16));
            $password ??= Str::password(16, true, true, false);
            $attributes = [
                'organization_id' => $organization->id,
                'customer_id' => $customer?->id,
                'access_plan_id' => $plan->id,
                'voucher_id' => $voucher?->id,
                'username' => $username,
                'password_cipher' => $password,
                'status' => 'active',
                'starts_at' => now(),
                'expires_at' => $voucher?->expires_at
                    ?? $plan->expiresAt(now(), $organization->timezone ?: config('app.timezone'))
                    ?? $customer?->expires_at,
            ];

            $credential = $voucher
                ? AccessCredential::updateOrCreate(['voucher_id' => $voucher->id], $attributes)
                : AccessCredential::create($attributes);

            DB::table('radcheck')->updateOrInsert(
                ['username' => $username, 'attribute' => 'Cleartext-Password'],
                ['op' => ':=', 'value' => $password],
            );
            DB::table('radcheck')->updateOrInsert(
                ['username' => $username, 'attribute' => 'Simultaneous-Use'],
                ['op' => ':=', 'value' => (string) $plan->simultaneous_use],
            );

            $timeout = $plan->duration_minutes ? $plan->duration_minutes * 60 : null;
            if ($credential->expires_at) {
                // Unix seconds avoid depending on the RADIUS server's timezone.
                DB::table('radcheck')->updateOrInsert(
                    ['username' => $username, 'attribute' => 'Expiration'],
                    ['op' => ':=', 'value' => (string) $credential->expires_at->timestamp],
                );
                $remaining = max(0, (int) now()->diffInSeconds($credential->expires_at));
                $timeout = $timeout === null ? $remaining : min($timeout, $remaining);
            }
            if ($timeout !== null) {
                $this->reply($username, 'Session-Timeout', (string) $timeout);
            }

            if ($plan->download_kbps || $plan->upload_kbps) {
                $upload = $plan->upload_kbps ?: $plan->download_kbps;
                $download = $plan->download_kbps ?: $plan->upload_kbps;
                $this->reply($username, 'Mikrotik-Rate-Limit', "{$upload}k/{$download}k");
            }

            if ($plan->data_limit_bytes) {
                $low = $plan->data_limit_bytes % 4294967296;
                $gigawords = intdiv($plan->data_limit_bytes, 4294967296);
                $this->reply($username, 'Mikrotik-Total-Limit', (string) $low);
                if ($gigawords > 0) {
                    $this->reply($username, 'Mikrotik-Total-Limit-Gigawords', (string) $gigawords);
                }
            }

            return $credential->refresh();
        });
    }

    public function revoke(AccessCredential $credential): void
    {
        DB::transaction(function () use ($credential) {
            $credential->update(['status' => 'revoked']);
            DB::table('radcheck')->where('username', $credential->username)->delete();
            DB::table('radreply')->where('username', $credential->username)->delete();
        });
    }

    private function reply(string $username, string $attribute, string $value): void
    {
        DB::table('radreply')->updateOrInsert(
            ['username' => $username, 'attribute' => $attribute],
            ['op' => ':=', 'value' => $value],
        );
    }
}
