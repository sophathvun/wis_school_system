<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScholarshipPrintSetting extends Model
{
    protected $table='tb_scholarship_print_setting';
    protected $fillable=['campus_id','academic_year_id','prepared_by_name','prepared_date','prepared_staff_id','prepared_position','checked_by_name','checked_date','checked_staff_id','checked_position','approved_by_name','approved_date','approved_staff_id','approved_position','updated_by'];
    protected $casts=['prepared_date'=>'date','checked_date'=>'date','approved_date'=>'date'];

    public static function requiresVerifier(?int $campusId): bool
    {
        if(!$campusId)return false;
        $name=SchoolInfo::whereKey($campusId)->value('campus_name_en');
        return in_array(strtoupper(trim((string)$name)),['CEN','CENTRAL','CENTRAL OFFICE'],true);
    }
}
