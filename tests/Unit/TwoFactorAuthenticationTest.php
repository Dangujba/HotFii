<?php

namespace Tests\Unit;

use App\Services\Auth\TwoFactorAuthentication;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    public function test_it_matches_the_rfc_totp_vector_at_six_digits(): void
    {
        $twoFactor = app(TwoFactorAuthentication::class);
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        $this->assertSame('287082', $twoFactor->currentCode($secret, 59));
        $this->assertTrue($twoFactor->verify($secret, '287082', 59));
        $this->assertFalse($twoFactor->verify($secret, '000000', 59));
    }

    public function test_it_accepts_only_the_adjacent_time_window(): void
    {
        $twoFactor = app(TwoFactorAuthentication::class);
        $secret = $twoFactor->generateSecret();
        $code = $twoFactor->currentCode($secret, 1_000_000);

        $this->assertTrue($twoFactor->verify($secret, $code, 1_000_030));
        $this->assertFalse($twoFactor->verify($secret, $code, 1_000_060));
    }
}
