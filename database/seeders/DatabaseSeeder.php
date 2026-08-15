<?php

namespace Database\Seeders;

use App\Domain\Enums\BillingPlan;
use App\Domain\Enums\MembershipRole;
use App\Domain\Enums\OrganizationMode;
use App\Domain\Enums\OrganizationStatus;
use App\Domain\Enums\RouterVendor;
use App\Models\Organization;
use App\Models\User;
use App\Services\Network\NetworkDeviceManager;
use App\Services\Vouchers\VoucherService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Demo seed skipped in production.');
            return;
        }

        $password = 'HotFii-Test-2026!';
        $owner = User::updateOrCreate(
            ['email' => 'owner@hotfii.test'],
            ['name' => 'HotFii Demo Owner', 'password' => $password, 'email_verified_at' => now()],
        );
        User::updateOrCreate(
            ['email' => 'admin@hotfii.test'],
            ['name' => 'HotFii Platform Admin', 'password' => $password, 'email_verified_at' => now(), 'is_platform_admin' => true],
        );

        $organization = Organization::firstOrCreate(
            ['slug' => 'hotfii-demo'],
            [
                'name' => 'HotFii Demo Network',
                'mode' => OrganizationMode::Hybrid,
                'status' => OrganizationStatus::Sandbox,
                'billing_plan' => BillingPlan::Organization20,
                'branding' => ['portal_name' => 'Demo Market Wi-Fi', 'primary_color' => '#146c43'],
            ],
        );
        $organization->users()->syncWithoutDetaching([
            $owner->id => ['role' => MembershipRole::Owner->value, 'joined_at' => now()],
        ]);

        $location = $organization->locations()->firstOrCreate(
            ['name' => 'Demo Market'],
            ['address' => 'Abuja, Nigeria'],
        );

        $hourPlan = $organization->accessPlans()->firstOrCreate(
            ['name' => '1 Hour'],
            ['access_type' => 'paid', 'price_kobo' => 30000, 'duration_minutes' => 60, 'download_kbps' => 10000, 'upload_kbps' => 3000, 'simultaneous_use' => 1, 'validity_days' => 1],
        );
        $organization->accessPlans()->firstOrCreate(
            ['name' => 'Day Pass'],
            ['access_type' => 'paid', 'price_kobo' => 100000, 'duration_minutes' => 1440, 'data_limit_bytes' => 5 * 1073741824, 'download_kbps' => 20000, 'upload_kbps' => 5000, 'simultaneous_use' => 1, 'validity_days' => 2],
        );
        $organization->accessPlans()->firstOrCreate(
            ['name' => 'Staff Access'],
            ['access_type' => 'internal', 'price_kobo' => 0, 'download_kbps' => 15000, 'upload_kbps' => 5000, 'simultaneous_use' => 2],
        );

        if (! $organization->networkDevices()->exists()) {
            app(NetworkDeviceManager::class)->create($location, [
                'name' => 'Demo Generic NAS',
                'vendor' => RouterVendor::Generic->value,
                'model' => 'RADIUS laboratory',
                'management_address' => '192.0.2.20',
            ]);
        }

        if (! $organization->voucherBatches()->exists()) {
            app(VoucherService::class)->createBatch($organization, $hourPlan, 8);
        }
    }
}