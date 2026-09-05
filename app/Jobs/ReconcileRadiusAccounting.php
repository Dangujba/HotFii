<?php

namespace App\Jobs;

use App\Domain\Enums\RouterVendor;
use App\Events\HotspotSessionUpdated;
use App\Models\AccessCredential;
use App\Models\HotspotSession;
use App\Models\NetworkDevice;
use App\Services\Network\NetworkDeviceManager;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ReconcileRadiusAccounting implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 90;

    public function __construct()
    {
        $this->onQueue('network');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('radius-accounting-reconciliation'))->expireAfter(120)];
    }

    public function backoff(): array
    {
        return [10, 60, 180];
    }

    public function handle(NetworkDeviceManager $devices): void
    {
        DB::table('radacct')
            ->whereRaw("(
                acctupdatetime >= CURRENT_TIMESTAMP - INTERVAL '10 minutes'
                OR acctstoptime >= CURRENT_TIMESTAMP - INTERVAL '10 minutes'
            )")
            ->orderBy('radacctid')
            ->chunkById(250, function ($records) use ($devices) {
                foreach ($records as $record) {
                    $nas = DB::table('nas')->where('nasname', $record->nasipaddress)->first();
                    $device = $nas?->network_device_id ? NetworkDevice::find($nas->network_device_id) : null;
                    $credential = AccessCredential::where('username', $record->username)->first();

                    if (! $device || ! $credential || $device->organization_id !== $credential->organization_id) {
                        continue;
                    }

                    $session = HotspotSession::updateOrCreate(
                        ['acct_session_id' => $record->acctsessionid],
                        [
                            'organization_id' => $device->organization_id,
                            'network_device_id' => $device->id,
                            'source' => $device->vendor === RouterVendor::Omada
                                ? 'omada'
                                : 'radius',
                            'customer_id' => $credential->customer_id,
                            'access_plan_id' => $credential->access_plan_id,
                            'radius_username' => $record->username,
                            'mac_address' => $record->callingstationid,
                            'ip_address' => $record->framedipaddress,
                            'status' => $record->acctstoptime ? 'stopped' : 'active',
                            'input_bytes' => (int) ($record->acctinputoctets ?? 0),
                            'output_bytes' => (int) ($record->acctoutputoctets ?? 0),
                            'started_at' => $record->acctstarttime ? Carbon::parse($record->acctstarttime) : now(),
                            'expires_at' => $credential->expires_at,
                            'stopped_at' => $record->acctstoptime ? Carbon::parse($record->acctstoptime) : null,
                            'terminate_cause' => $record->acctterminatecause,
                        ],
                    );

                    $credential->update(['last_used_at' => now()]);
                    $credential->customer?->update(['last_authenticated_at' => now()]);
                    $devices->markEvidence($device, 'radius_auth', 'A valid RADIUS authentication produced accounting traffic.');
                    $devices->markEvidence($device, 'accounting', 'Accounting-Start or interim traffic received.', ['session' => $session->uuid]);
                    HotspotSessionUpdated::dispatch($session);
                }
            }, 'radacctid');
    }
}