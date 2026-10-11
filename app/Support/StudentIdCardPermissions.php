<?php

namespace App\Support;

use App\Models\User;

class StudentIdCardPermissions
{
    public const MODULE = 'student-id-card-qr';

    public static function catalog(): array
    {
        return [
            'view' => 'View Student ID Cards',
            'generate-qr' => 'Generate Student ID Card QR Code',
            'print-card' => 'Print Individual Student ID Card',
            'print-class' => 'Print Whole Class Student ID Cards',
            'copy-link' => 'Copy Student ID Card Link',
            'edit-template' => 'Edit / Save / Discard / Restore ID Card Template (all years)',
        ];
    }

    public static function allows(?User $user, string $action): bool
    {
        if (!$user || !array_key_exists($action, self::catalog())) return false;
        if ($user->isSuperAdmin()) return true;
        foreach (array_unique(['students.view', self::MODULE . '.view', self::MODULE . '.' . $action]) as $code) {
            if (!$user->hasPermission($code, $user->active_campus_id)) return false;
        }
        return true;
    }
}
