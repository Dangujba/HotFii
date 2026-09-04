<?php

namespace App\Support;

/**
 * Kobo-to-naira formatting for the screen.
 *
 * Every money column in the database is an integer count of kobo. The platform
 * console reads a lot of them side by side, so the conversion lives here rather
 * than as `number_format($kobo / 100, 0)` repeated at every call site, where a
 * single missing division silently overstates a figure by a hundredfold.
 */
class Naira
{
    /** Whole naira, thousands separated: ₦1,250. */
    public static function from(?int $kobo): string
    {
        return '₦'.number_format((int) $kobo / 100, 0);
    }

    /** With kobo, for a figure somebody may reconcile against a statement. */
    public static function exact(?int $kobo): string
    {
        return '₦'.number_format((int) $kobo / 100, 2);
    }

    /** Bare naira as a float, for chart series and JSON. */
    public static function value(?int $kobo): float
    {
        return round((int) $kobo / 100, 2);
    }
}
