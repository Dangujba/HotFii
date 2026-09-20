<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TwoFactorAuthentication
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    public function provisioningUri(User $user, string $secret): string
    {
        $issuer = config('app.name', 'HotFii');
        $label = rawurlencode($issuer.':'.$user->email);

        return 'otpauth://totp/'.$label.'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function verify(string $secret, string $code, ?int $timestamp = null): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== 6) {
            return false;
        }

        $counter = intdiv($timestamp ?? time(), 30);
        foreach ([-1, 0, 1] as $window) {
            if (hash_equals($this->code($secret, $counter + $window), $code)) {
                return true;
            }
        }

        return false;
    }

    public function currentCode(string $secret, ?int $timestamp = null): string
    {
        return $this->code($secret, intdiv($timestamp ?? time(), 30));
    }

    public function verifyOrConsumeRecoveryCode(User $user, string $code): bool
    {
        if ($user->two_factor_secret && $this->verify($user->two_factor_secret, $code)) {
            return true;
        }

        $normalized = strtoupper(trim($code));
        $recoveryCodes = $user->two_factor_recovery_codes ?? [];
        foreach ($recoveryCodes as $index => $hash) {
            if (! Hash::check($normalized, $hash)) {
                continue;
            }

            unset($recoveryCodes[$index]);
            $user->forceFill(['two_factor_recovery_codes' => array_values($recoveryCodes)])->save();

            return true;
        }

        return false;
    }

    public function recoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn () => strtoupper(substr(bin2hex(random_bytes(4)), 0, 4).'-'.substr(bin2hex(random_bytes(4)), 0, 4)))
            ->all();
    }

    public function hashRecoveryCodes(array $codes): array
    {
        return array_map(fn (string $code) => Hash::make(strtoupper($code)), $codes);
    }

    private function code(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);
        $counterBytes = pack('N2', intdiv($counter, 4294967296), $counter & 0xFFFFFFFF);
        $hash = hash_hmac('sha1', $counterBytes, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($binary % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $value): string
    {
        $result = '';
        $buffer = 0;
        $bits = 0;
        foreach (unpack('C*', $value) as $byte) {
            $buffer = ($buffer << 8) | $byte;
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $result .= self::ALPHABET[($buffer >> $bits) & 31];
                $buffer &= (1 << $bits) - 1;
            }
        }
        if ($bits > 0) {
            $result .= self::ALPHABET[($buffer << (5 - $bits)) & 31];
        }

        return $result;
    }

    private function base32Decode(string $value): string
    {
        $result = '';
        $buffer = 0;
        $bits = 0;
        foreach (str_split(strtoupper(preg_replace('/\s+/', '', $value) ?? '')) as $character) {
            $position = strpos(self::ALPHABET, $character);
            if ($position === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $position;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $result .= chr(($buffer >> $bits) & 0xFF);
                $buffer &= (1 << $bits) - 1;
            }
        }

        return $result;
    }
}
