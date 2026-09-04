<?php

namespace Tests\Feature\Platform;

use App\Domain\Enums\BillingPlan;
use App\Domain\Enums\OrganizationMode;
use App\Domain\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The console has to open for a support-only admin.
 *
 * That is the case the shell is easy to break: an admin who belongs to no
 * organization has no current organization, so anything in the layout that
 * reaches for one takes every page down at once.
 */
class ConsoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_console_page_opens_for_an_admin_with_no_organization(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $organization = $this->organization();

        $this->assertFalse($admin->organizations()->exists());

        foreach ($this->pages($organization) as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_an_ordinary_operator_is_refused_every_console_page(): void
    {
        $operator = User::factory()->create();
        $organization = $this->organization();
        $organization->users()->attach($operator, ['role' => 'owner', 'joined_at' => now()]);

        foreach ($this->pages($organization) as $url) {
            $this->actingAs($operator)->get($url)->assertForbidden();
        }
    }

    public function test_a_guest_is_sent_to_login(): void
    {
        $this->get(route('platform.index'))->assertRedirect(route('login'));
    }

    /**
     * A deleted organization is still readable, because its money and audit rows
     * outlive it and this console is the only place left to read them.
     */
    public function test_a_deleted_organization_still_opens(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $organization = $this->organization();
        $organization->delete();

        $this->actingAs($admin)
            ->get(route('platform.organizations.show', $organization))
            ->assertOk()
            ->assertSee('This organization is deleted', false);
    }

    /**
     * @return list<string>
     */
    private function pages(Organization $organization): array
    {
        return [
            route('platform.index'),
            route('platform.organizations.index'),
            route('platform.organizations.show', $organization),
            route('platform.reviews.index'),
            route('platform.billing.index'),
            route('platform.transactions.index'),
            route('platform.users.index'),
            route('platform.audit.index'),
            route('platform.system.index'),
        ];
    }

    private function organization(): Organization
    {
        return Organization::create([
            'name' => 'Damaturu Mesh',
            'slug' => 'damaturu-mesh-'.Str::lower(Str::random(6)),
            'mode' => OrganizationMode::Commerce,
            'status' => OrganizationStatus::Sandbox,
            'billing_plan' => BillingPlan::Sandbox,
        ]);
    }
}
