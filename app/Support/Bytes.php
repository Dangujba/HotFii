<?php

namespace App\Support;

class Bytes
{
    private const MEGABYTE = 1048576;

    /**
     * Byte count in whichever unit reads naturally. A 500 MB figure shown as
     * "0.49 GB" tells an operator nothing, so anything under a gigabyte stays
     * in megabytes.
     */
    public static function human(?int $bytes): string
    {
        $megabytes = (float) ($bytes ?? 0) / self::MEGABYTE;

        if ($megabytes < 1024) {
            return number_format($megabytes, $megabytes > 0 && $megabytes < 10 ? 1 : 0).' MB';
        }

        return rtrim(rtrim(number_format($megabytes / 1024, 2), '0'), '.').' GB';
    }

    /**
     * As human(), but gigabyte figures carry the megabyte equivalent too. Use
     * this where the number is a quota someone entered in megabytes.
     */
    public static function detailed(?int $bytes): string
    {
        $megabytes = (float) ($bytes ?? 0) / self::MEGABYTE;

        return $megabytes < 1024
            ? self::human($bytes)
            : self::human($bytes).' ('.number_format($megabytes, 0).' MB)';
    }
}
