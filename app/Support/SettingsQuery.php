<?php

namespace App\Support;

use Illuminate\Http\Request;

final class SettingsQuery
{
    public static function perPage(Request $request, int $default = 10): int
    {
        $value = filter_var($request->query('perPage', $default), FILTER_VALIDATE_INT);

        return min(max($value ?: $default, 1), 100);
    }

    public static function search(Request $request): string
    {
        return trim((string) $request->query('search', ''));
    }

    public static function direction(Request $request, string $key = 'sortDir'): string
    {
        return $request->query($key) === 'desc' ? 'desc' : 'asc';
    }

    public static function sort(Request $request, array $allowed, string $default, string $key = 'sortBy'): string
    {
        $requested = (string) $request->query($key, $default);

        return in_array($requested, $allowed, true) ? $requested : $default;
    }
}
