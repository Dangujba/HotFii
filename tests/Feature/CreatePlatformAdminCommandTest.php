<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatePlatformAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_verified_platform_admin(): void
    {
        $this->artisan('hotfii:create-admin', ['email' => 'Boss@HotFii.com', '--name' => 'Baba Goni'])
            ->expectsQuestion('Password', 'a-long-enough-secret')
            ->expectsQuestion('Confirm password', 'a-long-enough-secret')
            ->assertSuccessful();

        $user = User::where('email', 'boss@hotfii.com')->firstOrFail();

        $this->assertTrue($user->is_platform_admin);
        $this->assertSame('Baba Goni', $user->name);
        // The platform routes are behind the `verified` middleware, so an
        // unverified admin could not reach the pages the flag exists for.
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform-admin.granted', 'subject_id' => $user->id]);
    }

    public function test_it_promotes_an_existing_user_without_touching_their_password(): void
    {
        $user = User::factory()->create(['email' => 'owner@hotfii.com']);
        $hash = $user->password;

        $this->artisan('hotfii:create-admin', ['email' => 'owner@hotfii.com'])
            ->expectsConfirmation('Set a new password?', 'no')
            ->assertSuccessful();

        $user->refresh();

        $this->assertTrue($user->is_platform_admin);
        $this->assertSame($hash, $user->password);
    }

    public function test_a_password_below_the_strength_rule_is_refused(): void
    {
        $this->artisan('hotfii:create-admin', ['email' => 'weak@hotfii.com'])
            ->expectsQuestion('Password', 'short')
            ->expectsQuestion('Confirm password', 'short')
            ->expectsQuestion('Password', 'short')
            ->expectsQuestion('Confirm password', 'short')
            ->expectsQuestion('Password', 'short')
            ->expectsQuestion('Confirm password', 'short')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'weak@hotfii.com']);
    }
}
