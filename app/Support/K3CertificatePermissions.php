<?php

namespace App\Support;

class K3CertificatePermissions
{
    public const SAVE_GIVEN_DATE = 'reports.k3.save-given-date';
    public const ASSIGN_NUMBERS = 'reports.k3.assign-numbers';
    public const EDIT_PREFIX = 'reports.k3.edit-prefix';
    public const EDIT_TEMPLATE = 'reports.k3.edit-template';

    public static function catalog(): array
    {
        return [
            self::SAVE_GIVEN_DATE => 'K3 Certificate: Save Given Date (year-wide)',
            self::ASSIGN_NUMBERS => 'K3 Certificate: Assign Certificate Numbers (year-wide)',
            self::EDIT_PREFIX => 'K3 Certificate: Edit Prefix (year-wide)',
            self::EDIT_TEMPLATE => 'K3 Certificate: Edit Template (all years)',
        ];
    }

    public static function forAction(string $action): ?string
    {
        return match ($action) {
            'save_date' => self::SAVE_GIVEN_DATE,
            'assign' => self::ASSIGN_NUMBERS,
            'update_prefix' => self::EDIT_PREFIX,
            'save_style', 'reset_style', 'save_template' => self::EDIT_TEMPLATE,
            default => null,
        };
    }
}
