<?php

namespace Tests\Unit;

use App\Domain\Enums\PlanValidityMode;
use App\Models\AccessPlan;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlanValidityTest extends TestCase
{
    public static function expiryExamples(): array
    {
        return [
            'one calendar day' => ['midnight', 1, '2026-09-07 23:00:00', 'Africa/Lagos', '2026-09-08 00:00:00'],
            'one calendar week' => ['midnight', 7, '2026-09-07 23:00:00', 'Africa/Lagos', '2026-09-14 00:00:00'],
            'one rolling day' => ['rolling', 1, '2026-09-07 23:00:00', 'Africa/Lagos', '2026-09-08 23:00:00'],
            'one rolling week' => ['rolling', 7, '2026-09-07 23:00:00', 'Africa/Lagos', '2026-09-14 23:00:00'],
            'midnight activation gets a full day' => ['midnight', 1, '2026-09-07 00:00:00', 'Africa/Lagos', '2026-09-08 00:00:00'],
            'organization timezone, not server timezone' => ['midnight', 1, '2026-09-07 23:00:00', 'Asia/Kolkata', '2026-09-08 00:00:00'],
            'calendar month boundary' => ['midnight', 7, '2026-09-28 23:00:00', 'Africa/Lagos', '2026-10-05 00:00:00'],
            'calendar DST boundary' => ['midnight', 1, '2026-03-08 00:00:00', 'America/New_York', '2026-03-09 00:00:00'],
            'rolling DST boundary stays 24 hours' => ['rolling', 1, '2026-03-08 00:00:00', 'America/New_York', '2026-03-09 01:00:00'],
        ];
    }

    #[DataProvider('expiryExamples')]
    public function test_validity_expiry(string $mode, int $days, string $activation, string $timezone, string $expected): void
    {
        config(['app.timezone' => 'UTC']);
        $plan = new AccessPlan(['validity_days' => $days, 'validity_mode' => $mode]);
        $activatedAt = CarbonImmutable::parse($activation, $timezone);
        $expiry = $plan->expiresAt($activatedAt, $timezone);

        $this->assertTrue($expiry->equalTo(CarbonImmutable::parse($expected, $timezone)));
        $this->assertSame('UTC', $expiry->timezoneName);
        $this->assertSame($activation, $activatedAt->format('Y-m-d H:i:s'));
    }

    public function test_new_plans_default_to_midnight_and_unset_validity_has_no_deadline(): void
    {
        $plan = new AccessPlan;
        $this->assertSame(PlanValidityMode::Midnight, $plan->validity_mode);
        $this->assertNull($plan->expiresAt(now(), 'Africa/Lagos'));
        $this->assertSame('No expiry', $plan->validityLabel());
        $plan->validity_days = 7;
        $this->assertSame('7 calendar days, expires at midnight', $plan->validityLabel());
        $plan->validity_mode = PlanValidityMode::Rolling;
        $this->assertSame('168 hours from activation', $plan->validityLabel());
    }
}
