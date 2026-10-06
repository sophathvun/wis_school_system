<?php

namespace App\Support;

class G9CertificatePermissions
{
    public const SAVE_GIVEN_DATE = 'reports.g9.save-given-date';
    public const ASSIGN_NUMBERS = 'reports.g9.assign-numbers';
    public const EDIT_PREFIX = 'reports.g9.edit-prefix';
    public const EDIT_TEMPLATE = 'reports.g9.edit-template';

    public static function catalog(): array
    {
        return [
            self::SAVE_GIVEN_DATE => 'G9 Certificate: Save Given Date (year-wide)',
            self::ASSIGN_NUMBERS => 'G9 Certificate: Assign Certificate Numbers (year-wide)',
            self::EDIT_PREFIX => 'G9 Certificate: Edit Prefix (year-wide)',
            self::EDIT_TEMPLATE => 'G9 Certificate: Edit Template (all years)',
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
