<?php

namespace Tests\Feature\Operator;

use App\Domain\Enums\BillingPlan;
use App\Domain\Enums\OrganizationMode;
use App\Domain\Enums\OrganizationStatus;
use App\Domain\Enums\PaymentStatus;
use App\Models\AccessPlan;
use App\Models\FeeLedgerEntry;
use App\Models\NetworkDevice;
use App\Models\Organization;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Vouchers\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RouterFilteringTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $user;

    private AccessPlan $plan;

    private NetworkDevice $mainRouter;

    private NetworkDevice $annexRouter;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

        $this->organization = Organization::create([
            'name' => 'Router Scope Network',
            'slug' => 'router-scope-'.Str::lower(Str::random(6)),
            'mode' => OrganizationMode::Commerce,
            'status' => OrganizationStatus::Live,
            'billing_plan' => BillingPlan::StandardSeller,
            'currency' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);
        $this->organization->forceFill([
            'trial_started_at' => now()->subMonths(2),
            'trial_ends_at' => now()->subMonth(),
        ])->save();

        $this->user = User::factory()->create();
        $this->organization->users()->attach($this->user, [
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $location = $this->organization->locations()->create([
            'name' => 'Main site',
            'timezone' => 'Africa/Lagos',
        ]);

        $this->mainRouter = $this->router($location->id, 'Main router');
        $this->annexRouter = $this->router($location->id, 'Annex router');

        $this->plan = $this->organization->accessPlans()->create([
            'name' => 'One Day',
            'access_type' => 'paid',
            'price_kobo' => 500_00,
            'duration_minutes' => 1440,
            'simultaneous_use' => 1,
        ]);
    }

    public function test_an_all_router_voucher_records_the_router_where_it_was_activated(): void
    {
        $service = app(VoucherService::class);
        $batch = $service->createBatch($this->organization, $this->plan, 1);
        $voucher = $batch->vouchers->sole();

        $service->redeem(
            $this->organization,
            $voucher->code_cipher,
            device: $this->mainRouter,
        );

        $this->assertNull($voucher->refresh()->network_device_id, 'All-router coverage must remain organization-wide.');
        $this->assertSame($this->mainRouter->id, $voucher->activated_network_device_id);
        $this->assertSame($this->mainRouter->id, Transaction::sole()->network_device_id);
        $this->assertSame($this->mainRouter->id, FeeLedgerEntry::sole()->network_device_id);
    }

    public function test_router_scope_filters_operator_pages_and_csv_export(): void
    {
        $mainSale = $this->sale($this->mainRouter, 'HF-CASH-MAIN', 500_00);
        $annexSale = $this->sale($this->annexRouter, 'HF-CASH-ANNEX', 900_00);
        $this->ledger($mainSale);
        $this->ledger($annexSale);

        $mainBatch = app(VoucherService::class)->createBatch(
            $this->organization,
            $this->plan,
            1,
            device: $this->mainRouter,
        );
        app(VoucherService::class)->createBatch(
            $this->organization,
            $this->plan,
            1,
            device: $this->annexRouter,
        );

        $session = ['current_organization_id' => $this->organization->id];
        $query = ['router' => $this->mainRouter->id];

        $sales = $this->actingAs($this->user)->withSession($session)->get(route('sales.index', $query));
        $sales->assertOk();
        $this->assertSame([$mainSale->id], $sales->viewData('transactions')->pluck('id')->all());
        $this->assertSame(500_00, $sales->viewData('totals')['cash']);

        $finance = $this->withSession($session)->get(route('finance.index', $query));
        $finance->assertOk();
        $this->assertSame([$mainSale->id], $finance->viewData('entries')->pluck('source_id')->all());
        $this->assertSame(500_00, $finance->viewData('current')['sales']);
        $allFinance = $this->withSession($session)->get(route('finance.index'));
        $this->assertSame(
            $allFinance->viewData('current')['estimated_invoice_balance'],
            $finance->viewData('current')['estimated_invoice_balance'],
            'The organization invoice estimate must not change when viewing one router.',
        );

        $reports = $this->withSession($session)->get(route('reports.index', [
            ...$query,
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
        ]));
        $reports->assertOk();
        $this->assertSame(1, (int) $reports->viewData('summary')->sales);
        $this->assertSame(500_00, (int) $reports->viewData('summary')->gross_kobo);

        $dashboard = $this->withSession($session)->get(route('dashboard', $query));
        $dashboard->assertOk();
        $this->assertSame([$mainSale->id], $dashboard->viewData('transactions')->pluck('id')->all());
        $this->assertSame(500.0, $dashboard->viewData('revenue')['total']);

        $vouchers = $this->withSession($session)->get(route('vouchers.index', $query));
        $vouchers->assertOk();
        $this->assertSame([$mainBatch->id], $vouchers->viewData('batches')->pluck('id')->all());

        $csv = $this->withSession($session)->get(route('reports.export', [
            ...$query,
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
        ]));
        $csv->assertOk();
        $content = $csv->streamedContent();
        $this->assertStringContainsString('Main router', $content);
        $this->assertStringContainsString('HF-CASH-MAIN', $content);
        $this->assertStringNotContainsString('Annex router', $content);
        $this->assertStringNotContainsString('HF-CASH-ANNEX', $content);
    }

    private function router(int $locationId, string $name): NetworkDevice
    {
        return $this->organization->networkDevices()->create([
            'location_id' => $locationId,
            'name' => $name,
            'vendor' => 'generic',
            'adapter' => 'generic-radius',
            'support_level' => 'compatible',
            'status' => 'online',
            'nas_identifier' => 'hf-'.Str::lower(Str::random(12)),
            'radius_secret' => Str::random(32),
        ]);
    }

    private function sale(NetworkDevice $router, string $reference, int $amountKobo): Transaction
    {
        return Transaction::create([
            'organization_id' => $this->organization->id,
            'network_device_id' => $router->id,
            'access_plan_id' => $this->plan->id,
            'reference' => $reference,
            'provider' => 'manual',
            'channel' => 'cash',
            'status' => PaymentStatus::Successful,
            'gross_amount_kobo' => $amountKobo,
            'platform_fee_kobo' => intdiv($amountKobo * 200, 10_000),
            'billable_sales_kobo' => $amountKobo,
            'paid_at' => now(),
        ]);
    }

    private function ledger(Transaction $transaction): void
    {
        FeeLedgerEntry::create([
            'organization_id' => $this->organization->id,
            'network_device_id' => $transaction->network_device_id,
            'source_type' => 'transaction',
            'source_id' => $transaction->id,
            'billing_period' => now()->startOfMonth(),
            'billable_sales_kobo' => $transaction->billable_sales_kobo,
            'fee_amount_kobo' => $transaction->platform_fee_kobo,
            'status' => 'accrued',
        ]);
    }
}
