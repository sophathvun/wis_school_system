<?php

namespace App\Support;

final class PhoneNumber
{
    public static function formatCambodian(?string $value): string
    {
        $value = trim((string) $value);
        $compact = preg_replace('/[\s().-]+/', '', $value) ?? $value;
        if (!preg_match('/^(0|\+?855|00855)([1-9][0-9]{7,8})$/', $compact, $matches)) {
            return $value;
        }

        $number = $matches[2];
        $prefix = $matches[1] === '0' ? '0' : '+855 ';

        return $prefix . substr($number, 0, 2) . ' ' . substr($number, 2, 3) . ' ' . substr($number, 5);
    }

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
