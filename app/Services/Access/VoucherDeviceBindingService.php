<?php

namespace App\Services\Access;

use App\Domain\Enums\VoucherStatus;
use App\Models\NetworkDevice;
use App\Models\Voucher;
use App\Models\VoucherDeviceBinding;
use Illuminate\Support\Facades\DB;

class VoucherDeviceBindingService
{
    public function __construct(
        private readonly AllowanceService $allowances,
    ) {}

    public function normalizeMac(?string $mac): ?string
    {
        if (! is_string($mac) || trim($mac) === '') {
            return null;
        }

        $hex = strtoupper(
            preg_replace(
                '/[^0-9A-F]/i',
                '',
                $mac
            )
        );

        if (strlen($hex) !== 12) {
            return null;
        }

        return implode(
            ':',
            str_split($hex, 2)
        );
    }

    public function bind(
        NetworkDevice $device,
        Voucher $voucher,
        ?string $mac
    ): ?VoucherDeviceBinding {
        $normalizedMac =
            $this->normalizeMac($mac);

        if (! $normalizedMac) {
            return null;
        }

        $voucher->loadMissing(
            'credential.accessPlan'
        );

        $credential =
            $voucher->credential;

        if (! $credential) {
            return null;
        }

        return DB::transaction(function () use (
            $device,
            $voucher,
            $credential,
            $normalizedMac
        ) {
            $binding =
                VoucherDeviceBinding::query()
                    ->where(
                        'network_device_id',
                        $device->id
                    )
                    ->where(
                        'mac_address',
                        $normalizedMac
                    )
                    ->lockForUpdate()
                    ->first();

            if (! $binding) {
                $binding =
                    new VoucherDeviceBinding([
                        'network_device_id' =>
                            $device->id,

                        'mac_address' =>
                            $normalizedMac,

                        'first_seen_at' =>
                            now(),
                    ]);
            }

            /*
             * Prefer the earliest expiry known to HotFii.
             */
            $expiresAt = null;

            foreach ([
                $voucher->expires_at,
                $credential->expires_at,
            ] as $candidate) {
                if (
                    $candidate
                    && (
                        ! $expiresAt
                        || $candidate->lt($expiresAt)
                    )
                ) {
                    $expiresAt =
                        $candidate->copy();
                }
            }

            $binding->fill([
                'organization_id' =>
                    $device->organization_id,

                'voucher_id' =>
                    $voucher->id,

                'access_credential_id' =>
                    $credential->id,

                'status' =>
                    'active',

                'last_seen_at' =>
                    now(),

                'expires_at' =>
                    $expiresAt,
            ]);

            $binding->save();

            return $binding->refresh();
        });
    }

    public function resumableFor(
        NetworkDevice $device,
        ?string $mac
    ): ?Voucher {
        $normalizedMac =
            $this->normalizeMac($mac);

        if (! $normalizedMac) {
            return null;
        }

        $binding =
            VoucherDeviceBinding::query()
                ->with([
                    'voucher.credential.accessPlan',
                    'voucher.batch.accessPlan',
                ])
                ->where(
                    'organization_id',
                    $device->organization_id
                )
                ->where(
                    'network_device_id',
                    $device->id
                )
                ->where(
                    'mac_address',
                    $normalizedMac
                )
                ->where(
                    'status',
                    'active'
                )
                ->first();

        if (! $binding) {
            return null;
        }

        $voucher =
            $binding->voucher;

        $credential =
            $voucher?->credential;

        if (
            ! $voucher
            || ! $credential
        ) {
            $this->disable($binding);

            return null;
        }

        /*
         * Voucher itself must still be active.
         */
        if (
            $voucher->status !==
                VoucherStatus::Active
        ) {
            $this->disable($binding);

            return null;
        }

        /*
         * Check voucher validity.
         */
        if (
            $voucher->expires_at?->isPast()
        ) {
            $this->disable($binding);

            return null;
        }

        /*
         * Check the RADIUS credential too.
         */
        $credentialStatus =
            $credential->status instanceof \BackedEnum
                ? $credential->status->value
                : (string) $credential->status;

        if ($credentialStatus !== 'active') {
            $this->disable($binding);

            return null;
        }

        if (
            $credential->expires_at?->isPast()
        ) {
            $this->disable($binding);

            return null;
        }

        if (
            $binding->expires_at?->isPast()
        ) {
            $this->disable($binding);

            return null;
        }

        /*
         * Capped plans are also resumable while allowance remains.
         * Unlimited plans naturally return null remaining limits.
         */
        $allowance =
            $this->allowances
                ->forCredential($credential);

        if (
            $allowance
            && $allowance['remaining_bytes'] !== null
            && $allowance['remaining_bytes'] <= 0
        ) {
            $this->disable($binding);

            return null;
        }

        if (
            $allowance
            && $allowance['remaining_seconds'] !== null
            && $allowance['remaining_seconds'] <= 0
        ) {
            $this->disable($binding);

            return null;
        }

        $binding->update([
            'last_seen_at' => now(),
        ]);

        return $voucher;
    }

    private function disable(
        VoucherDeviceBinding $binding
    ): void {
        $binding->update([
            'status' => 'inactive',
            'last_seen_at' => now(),
        ]);
    }
}
