<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkippingGradeCampusSetting extends Model
{
    protected $table = 'tb_skipping_grade_campus_settings';
    protected $fillable = ['campus_id', 'committee_names', 'updated_by'];
    protected $casts = ['committee_names'=>'array'];

    public static function namesFor(int $campusId): array
    {
        $names=self::where('campus_id',$campusId)->first()?->committee_names ?? [];
        return array_replace(array_fill_keys(array_keys(SkippingGradeSetting::CAMPUS_COMMITTEE),''),$names);
    }
}
