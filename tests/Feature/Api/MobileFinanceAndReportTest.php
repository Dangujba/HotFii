<?php

namespace Tests\Feature\Api;

use App\Models\FeeLedgerEntry;
use App\Models\Invoice;
use App\Models\NetworkDevice;
use App\Models\Organization;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileFinanceAndReportTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $owner;

    private NetworkDevice $router;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.paystack.secret' => 'sk_test_hotfii',
            'services.paystack.url' => 'https://api.paystack.test',
            'hotfii.internal_plans.micro_seller.price_kobo' => 0,
            'hotfii.commerce.minimum_included_sales_kobo' => 5_000_000,
            'hotfii.commerce.standard_minimum_kobo' => 250_000,
            'hotfii.commerce.platform_fee_bps' => 200,
        ]);
        $this->organization = $this->organization('Finance Operator');
        $this->organization->forceFill(['trial_started_at' => now()->subDays(5)])->save();
        $this->owner = User::factory()->create();
        $this->organization->users()->attach($this->owner, ['role' => 'owner', 'joined_at' => now()]);
        $this->router = $this->router($this->organization, 'Main router');
    }

    public function test_finance_matches_billing_rules_and_has_independent_paginated_lists(): void
    {
        $otherRouter = $this->router($this->organization, 'Annex');
        $this->fee($this->router, 4_000_000, 80_000, 'accrued');
        $this->fee($otherRouter, 2_000_000, 40_000, 'collected');
        $invoice = $this->invoice($this->organization, 'open', 290_000);

        $response = $this->withToken($this->token($this->owner))->getJson(route(
            'api.v1.mobile.organizations.finance.index',
            [
                'organization' => $this->organization,
                'router' => $this->router->uuid,
                'ledger_page' => 1,
                'invoice_page' => 1,
            ],
        ))->assertOk();

        $response
            ->assertJsonPath('data.current.sales', 4_000_000)
            ->assertJsonPath('data.current.accrued', 80_000)
            ->assertJsonPath('data.current.collected', 0)
            ->assertJsonPath('data.current.estimated_month_end_fee', 270_000)
            ->assertJsonPath('data.current.estimated_invoice_balance', 230_000)
            ->assertJsonPath('data.ledger_pagination.total', 1)
            ->assertJsonPath('data.invoice_pagination.total', 1)
            ->assertJsonPath('data.invoices.0.id', $invoice->uuid)
            ->assertJsonPath('data.permissions.can_pay_invoices', true);
    }

    public function test_viewer_can_read_finance_but_cannot_start_payment_and_foreign_invoice_is_hidden(): void
    {
        $viewer = User::factory()->create();
        $this->organization->users()->attach($viewer, ['role' => 'viewer', 'joined_at' => now()]);
        $invoice = $this->invoice($this->organization, 'open', 250_000);
        $foreign = $this->organization('Foreign');
        $foreignInvoice = $this->invoice($foreign, 'open', 250_000);
        $token = $this->token($viewer);

        $this->withToken($token)
            ->getJson(route('api.v1.mobile.organizations.finance.index', $this->organization))
            ->assertOk()
            ->assertJsonPath('data.permissions.can_pay_invoices', false);
        $this->withToken($token)->postJson(route('api.v1.mobile.organizations.finance.invoices.pay', [
            'organization' => $this->organization,
            'invoice' => $invoice,
        ]))->assertForbidden();
        $this->withToken($token)->getJson(route('api.v1.mobile.organizations.finance.invoices.show', [
            'organization' => $this->organization,
            'invoice' => $foreignInvoice,
        ]))->assertNotFound();
    }

    public function test_invoice_checkout_uses_a_signed_callback_and_settles_once(): void
    {
        $invoice = $this->invoice($this->organization, 'open', 250_000);
        $callback = null;
        Http::fake([
            'https://api.paystack.test/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.test/hotfii'],
            ]),
            'https://api.paystack.test/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['status' => 'success', 'amount' => 250_000],
            ]),
        ]);

        $checkout = $this->withToken($this->token($this->owner))->postJson(route(
            'api.v1.mobile.organizations.finance.invoices.pay',
            ['organization' => $this->organization, 'invoice' => $invoice],
        ))->assertOk()->assertJsonPath('data.authorization_url', 'https://checkout.paystack.test/hotfii');
        $reference = $checkout->json('data.reference');
        Http::assertSent(function (Request $request) use (&$callback): bool {
            if (! str_ends_with($request->url(), '/transaction/initialize')) {
                return true;
            }
            $callback = $request->data()['callback_url'] ?? null;

            return is_string($callback) && str_contains($callback, '/mobile/invoice-payments/');
        });
        $this->assertNotNull($callback);

        $this->get($callback.'&reference='.urlencode($reference))
            ->assertRedirectContains('hotfii://invoice-payment?status=paid');
        $this->assertTrue($invoice->fresh()->isPaid());
    }

    public function test_reports_return_real_chart_data_and_authenticated_exports(): void
    {
        $plan = $this->organization->accessPlans()->create([
            'name' => 'One Day',
            'access_type' => 'paid',
            'price_kobo' => 500_00,
            'duration_minutes' => 1440,
            'simultaneous_use' => 1,
            'validity_days' => 1,
            'validity_mode' => 'midnight',
            'is_active' => true,
        ]);
        foreach ([
            ['HF-ONLINE-1', 'online', 100_000],
            ['HF-VCH-1', 'cash', 50_000],
            ['HF-CASH-1', 'cash', 25_000],
        ] as [$reference, $channel, $amount]) {
            Transaction::create([
                'organization_id' => $this->organization->id,
                'network_device_id' => $this->router->id,
                'access_plan_id' => $plan->id,
                'reference' => $reference,
                'channel' => $channel,
                'status' => 'successful',
                'gross_amount_kobo' => $amount,
                'billable_sales_kobo' => $amount,
                'paid_at' => now(),
            ]);
        }
        $this->organization->sessions()->create([
            'network_device_id' => $this->router->id,
            'radius_username' => 'report-user',
            'acct_session_id' => (string) Str::uuid(),
            'status' => 'active',
            'input_bytes' => 1024,
            'output_bytes' => 2048,
            'started_at' => now(),
        ]);
        $token = $this->token($this->owner);
        $parameters = [
            'organization' => $this->organization,
            'router' => $this->router->uuid,
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
        ];

        $this->withToken($token)->getJson(route('api.v1.mobile.organizations.reports.index', $parameters))
            ->assertOk()
            ->assertJsonPath('data.summary.sales', 3)
            ->assertJsonPath('data.summary.gross_kobo', 175_000)
            ->assertJsonPath('data.usage.sessions', 1)
            ->assertJsonPath('data.channels.0.total_kobo', 100_000)
            ->assertJsonPath('data.channels.1.total_kobo', 50_000)
            ->assertJsonPath('data.channels.2.total_kobo', 25_000)
            ->assertJsonPath('data.top_plans.0.name', 'One Day');
        $this->withToken($token)->get(route('api.v1.mobile.organizations.reports.export.csv', $parameters))
            ->assertOk()->assertDownload();
        $this->withToken($token)->get(route('api.v1.mobile.organizations.reports.export.pdf', $parameters))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    private function organization(string $name): Organization
    {
        return Organization::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'mode' => 'commerce',
            'status' => 'trial',
            'billing_plan' => 'micro_seller',
            'currency' => 'NGN',
            'timezone' => 'Africa/Lagos',
        ]);
    }

    private function router(Organization $organization, string $name): NetworkDevice
    {
        $location = $organization->locations()->create(['name' => $name.' site']);

        return $organization->networkDevices()->create([
            'location_id' => $location->id,
            'name' => $name,
            'vendor' => 'generic',
            'adapter' => 'generic-radius',
            'support_level' => 'compatible',
            'status' => 'online',
            'nas_identifier' => 'hf-'.Str::lower(Str::random(12)),
            'radius_secret' => Str::random(32),
        ]);
    }

    private function fee(NetworkDevice $router, int $sales, int $fee, string $status): FeeLedgerEntry
    {
        return FeeLedgerEntry::create([
            'organization_id' => $this->organization->id,
            'network_device_id' => $router->id,
            'source_type' => 'transaction',
            'source_id' => random_int(100, 999999),
            'billing_period' => now()->startOfMonth(),
            'billable_sales_kobo' => $sales,
            'fee_amount_kobo' => $fee,
            'status' => $status,
        ]);
    }

    private function invoice(Organization $organization, string $status, int $total): Invoice
    {
        return Invoice::create([
            'organization_id' => $organization->id,
            'number' => 'INV-'.Str::upper(Str::random(10)),
            'billing_period' => now()->subMonth()->startOfMonth(),
            'subtotal_kobo' => $total,
            'total_kobo' => $total,
            'status' => $status,
            'due_at' => now()->addDays(5),
        ]);
    }

    private function token(User $user): string
    {
        return $user->createToken('android:test:'.Str::random(8), ['mobile'])->plainTextToken;
    }
}
