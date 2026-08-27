<?php

namespace App\Support;

final class PhoneNumber
{
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if ($digits === '') {
            return $value;
        }
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '855')) {
            return '+' . $digits;
        }
        if (str_starts_with($digits, '0')) {
            return '+855' . substr($digits, 1);
        }
        return '+855' . $digits;
    }
}
