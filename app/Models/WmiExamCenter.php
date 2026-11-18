<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{DB,Schema};

class WmiExamCenter extends Model
{
    protected $fillable=['wmi_year_id','name','name_key','location'];
    protected $casts=['wmi_year_id'=>'integer'];

    public function wmiYear() { return $this->belongsTo(WmiYear::class); }

    /** Future WMI tables must use wmi_exam_center_id with a restrictive foreign key. */
    public static function linkedIds(array $ids): array
    {
        if (!$ids) return [];
        $linked=[];
        foreach (Schema::getTables() as $table) {
            $name=$table['name'];
            if (!str_starts_with($name,'wmi_') || !Schema::hasColumn($name,'wmi_exam_center_id')) continue;
            $linked=[...$linked,...DB::table($name)->whereIn('wmi_exam_center_id',$ids)->distinct()->pluck('wmi_exam_center_id')->all()];
        }
        return array_values(array_unique(array_map('intval',$linked)));
    }
}
