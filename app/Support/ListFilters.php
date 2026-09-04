<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Query-string readers for the list pages.
 *
 * Filters reach a list as often from a bookmark or a shared link as from the
 * form itself, so anything unrecognised is dropped rather than rejected: a
 * stale URL should show an unfiltered list, never a validation error on a page
 * somebody only wanted to read.
 */
class ListFilters
{
    public static function text(Request $request, string $key): string
    {
        return trim((string) $request->query($key));
    }

    /**
     * @param  array<int, string>  $allowed
     */
    public static function choice(Request $request, string $key, array $allowed): string
    {
        $value = trim((string) $request->query($key));

        return in_array($value, $allowed, true) ? $value : '';
    }

    public static function id(Request $request, string $key): ?int
    {
        $value = $request->query($key);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * A date input submits Y-m-d. Anything else is a hand-edited URL, so it is
     * ignored instead of reaching Carbon and throwing.
     */
    public static function date(Request $request, string $key): ?string
    {
        $value = trim((string) $request->query($key));

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $value) === 1 ? $value : null;
    }

    /**
     * A month input submits Y-m.
     */
    public static function month(Request $request, string $key): ?string
    {
        $value = trim((string) $request->query($key));

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1 ? $value : null;
    }

    /**
     * Case values of a string-backed enum, ready to use as a whitelist.
     *
     * @param  class-string  $enum
     * @return array<int, string>
     */
    public static function enumValues(string $enum): array
    {
        return array_column($enum::cases(), 'value');
    }

    /**
     * True when at least one filter is set, so a view can offer Clear only when
     * there is something to clear.
     *
     * @param  array<string, mixed>  $filters
     */
    public static function any(array $filters): bool
    {
        foreach ($filters as $value) {
            if ($value !== null && $value !== '' && $value !== []) {
                return true;
            }
        }

        return false;
    }
}
