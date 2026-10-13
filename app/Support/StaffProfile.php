<?php

namespace App\Support;

use Carbon\Carbon;

class StaffProfile
{
    public const DAYS = ['mon'=>'Monday','tue'=>'Tuesday','wed'=>'Wednesday','thu'=>'Thursday','fri'=>'Friday','sat'=>'Saturday','sun'=>'Sunday'];
    public const PERIODS = ['off'=>'Not Working','morning'=>'Morning Only','afternoon'=>'Afternoon Only','full_day'=>'Full Day'];
    public const CONTRACTS = ['permanent'=>'Permanent', 'fixed_term'=>'Fixed-Term', 'casual'=>'Casual', 'internship'=>'Internship', 'consultant'=>'Consultant'];
    public const IDENTITY_DOCUMENTS = ['national_id'=>'National ID', 'passport'=>'Passport', 'other'=>'Other'];
    public const STATUS = ['active'=>'Active','resigned'=>'Resigned','terminated'=>'Terminated','suspended'=>'Suspended','on_leave'=>'On Leave','retired'=>'Retired'];
    public const CHANGES = ['work_update'=>'Work Information Update','promotion'=>'Promotion','position_transfer'=>'Position Transfer','campus_transfer'=>'Campus Transfer','resignation'=>'Resignation','termination'=>'Termination'];
    public const GENDER_KH = ['Male'=>'ប្រុស','Female'=>'ស្រី','Other'=>'ផ្សេងៗ'];
    public const MARITAL = ['single'=>'Single','married'=>'Married','divorced'=>'Divorced','widowed'=>'Widowed'];
    public const MARITAL_KH = ['single'=>'នៅលីវ','married'=>'រៀបការ','divorced'=>'លែងលះ','widowed'=>'មេម៉ាយ / ពោះម៉ាយ'];

    public static function digits(?string $value): string
    {
        return strtr($value ?? '', array_combine(range(0,9), ['០','១','២','៣','៤','៥','៦','៧','៨','៩']));
    }

    public static function dateKhmer($value): string
    {
        if (!$value) return '';
        $date = Carbon::parse($value);
        $months = ['មករា','កុម្ភៈ','មីនា','មេសា','ឧសភា','មិថុនា','កក្កដា','សីហា','កញ្ញា','តុលា','វិច្ឆិកា','ធ្នូ'];
        return self::digits($date->format('d').' '.$months[$date->month-1].' '.$date->format('Y'));
    }

    public static function nationalityNote(?string $english, ?string $khmer = null): string
    {
        $value = mb_strtolower(trim($english ?? ''));
        if (!$value && !trim($khmer ?? '')) return '';
        return in_array($value,['cambodian','cambodia','khmer','kh','khm'],true) || (!$value && in_array(trim($khmer ?? ''),['ខ្មែរ','កម្ពុជា'],true))
            ? 'Cambodian' : 'International';
    }
}
