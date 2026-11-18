<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{DB,Schema};

class WmiSchool extends Model
{
    protected $fillable=['name_en','name_kh','name_key','school_type','province_id','remark'];
    public function province() { return $this->belongsTo(Province::class); }

    /** WMI registrations must reference schools through wmi_school_id with a restrictive foreign key. */
    public static function linkedIds(array $ids): array
    {
        if (!$ids) return [];
        $linked=[];
        foreach (Schema::getTables() as $table) {
            $name=$table['name'];
            if (!str_starts_with($name,'wmi_') || !Schema::hasColumn($name,'wmi_school_id')) continue;
            $linked=[...$linked,...DB::table($name)->whereIn('wmi_school_id',$ids)->distinct()->pluck('wmi_school_id')->all()];
        }
        return array_values(array_unique(array_map('intval',$linked)));
    }
}
