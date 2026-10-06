<?php

namespace App\Support;

class G12CertificatePermissions
{
    public const SAVE_GIVEN_DATE = 'reports.g12.save-given-date';
    public const ASSIGN_NUMBERS = 'reports.g12.assign-numbers';
    public const EDIT_PREFIX = 'reports.g12.edit-prefix';
    public const EDIT_TEMPLATE = 'reports.g12.edit-template';

    public static function catalog(): array
    {
        return [
            self::SAVE_GIVEN_DATE => 'G12 Certificate: Save Given Date (year-wide)',
            self::ASSIGN_NUMBERS => 'G12 Certificate: Assign Certificate Numbers (year-wide)',
            self::EDIT_PREFIX => 'G12 Certificate: Edit Prefix (year-wide)',
            self::EDIT_TEMPLATE => 'G12 Certificate: Edit Template (all years)',
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
