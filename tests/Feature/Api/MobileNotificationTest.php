<?php

namespace Tests\Feature\Api;

use App\Models\MobileDevice;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\HotFiiAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_read_filter_and_mark_tenant_notifications(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $organization = $this->organization('Home Network');
        $other = $this->organization('Other Network');
        $organization->users()->attach($user, ['role' => 'owner', 'joined_at' => now()]);
        $other->users()->attach($user, ['role' => 'owner', 'joined_at' => now()]);
        $user->notifyNow(new HotFiiAlert(
            'Router offline: Main',
            'No heartbeat has been received.',
            category: 'router',
            organizationId: $organization->uuid,
        ));
        $user->notifyNow(new HotFiiAlert(
            'Other invoice',
            'This belongs to another organization.',
            category: 'invoice',
            organizationId: $other->uuid,
        ));
        $token = $this->token($user);

        $response = $this->withToken($token)->getJson(route(
            'api.v1.mobile.organizations.notifications.index',
            ['organization' => $organization, 'category' => 'router'],
        ))->assertOk()
            ->assertJsonPath('data.notifications.0.title', 'Router offline: Main')
            ->assertJsonPath('data.notifications.0.category', 'router')
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonCount(1, 'data.notifications')
            ->assertJsonPath('data.preferences.push_enabled', true);

        $notificationId = $response->json('data.notifications.0.id');
        $this->withToken($token)->postJson(route(
            'api.v1.mobile.organizations.notifications.read',
            $organization,
        ), ['notification_id' => $notificationId])->assertOk();

        $this->assertNotNull($user->notifications()->findOrFail($notificationId)->read_at);
        $this->assertNull($user->notifications()->where('data', 'like', '%Other invoice%')->firstOrFail()->read_at);
    }

    public function test_operator_can_update_notification_preferences(): void
    {
        $user = User::factory()->create();
        $organization = $this->organization('Preference Network');
        $organization->users()->attach($user, ['role' => 'manager', 'joined_at' => now()]);

        $this->withToken($this->token($user))->patchJson(route(
            'api.v1.mobile.organizations.notifications.preferences.update',
            $organization,
        ), [
            'push_enabled' => true,
            'router_alerts' => false,
            'payment_alerts' => true,
            'invoice_alerts' => false,
            'account_alerts' => true,
        ])->assertOk()
            ->assertJsonPath('data.preferences.router_alerts', false)
            ->assertJsonPath('data.preferences.invoice_alerts', false);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'router_alerts' => false,
            'invoice_alerts' => false,
        ]);
    }

    public function test_mobile_device_registration_is_private_and_logout_removes_it(): void
    {
        $user = User::factory()->create();
        $organization = $this->organization('Device Network');
        $organization->users()->attach($user, ['role' => 'owner', 'joined_at' => now()]);
        $deviceId = 'physical-device-123';
        $hash = hash('sha256', $deviceId);
        $token = $user->createToken('android:Pixel 8:'.substr($hash, 0, 16), ['mobile'])->plainTextToken;

        $this->withToken($token)->putJson(route('api.v1.mobile.device.update'), [
            'device_id' => $deviceId,
            'device_name' => 'Google Pixel 8 Pro',
            'push_token' => 'fcm-private-token',
            'app_version' => '0.1.0',
            'os_version' => '16',
        ])->assertOk()->assertJsonMissing(['push_token' => 'fcm-private-token']);

        $this->assertDatabaseHas('mobile_devices', [
            'user_id' => $user->id,
            'device_identifier_hash' => $hash,
            'push_token_hash' => hash('sha256', 'fcm-private-token'),
        ]);
        $this->assertNotSame('fcm-private-token', DB::table('mobile_devices')->value('push_token'));
        $this->assertSame('fcm-private-token', MobileDevice::firstOrFail()->push_token);

        $this->withToken($token)->deleteJson(route('api.v1.mobile.auth.logout'))->assertNoContent();
        $this->assertDatabaseCount('mobile_devices', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
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

    private function token(User $user): string
    {
        return $user->createToken('android:test:'.Str::random(16), ['mobile'])->plainTextToken;
    }
}
