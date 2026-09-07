<?php

namespace App\Services\Vouchers;

use App\Domain\Enums\VoucherPinFormat;
use Generator;

class VoucherPinGenerator
{
    public const LENGTHS = [2, 4, 6, 8, 10, 12];

    public function capacity(VoucherPinFormat $format, int $length): int
    {
        return strlen($format->alphabet()) ** $length;
    }

    public function candidates(VoucherPinFormat $format, int $length, bool $dashed, int $quantity): Generator
    {
        $alphabet = $format->alphabet();
        $base = strlen($alphabet);
        $capacity = $this->capacity($format, $length);

        // Exhaust small spaces without random retries or repeating a candidate.
        // Larger PINs have ample space; bound retries even if collisions occur.
        $pool = $capacity <= 10000 ? range(0, $capacity - 1) : null;
        $attempts = $pool === null ? $quantity + 1000 : $capacity;
        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            $pin = '';
            if ($pool !== null) {
                $last = count($pool) - 1;
                $index = random_int(0, $last);
                $value = $pool[$index];
                $pool[$index] = $pool[$last];
                array_pop($pool);
                for ($position = 0; $position < $length; $position++) {
                    $pin = $alphabet[$value % $base].$pin;
                    $value = intdiv($value, $base);
                }
            } else {
                for ($position = 0; $position < $length; $position++) {
                    $pin .= $alphabet[random_int(0, $base - 1)];
                }
            }

            yield $dashed ? implode('-', str_split($pin, 4)) : $pin;
        }
    }
}
