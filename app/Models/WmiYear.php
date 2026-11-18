<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{DB, Schema};

class WmiYear extends Model
{
    protected $fillable = ['year'];
    protected $casts = ['year'=>'integer'];

    /** WMI child tables must use wmi_year_id and a restrictive foreign key. */
    public static function linkedYearIds(array $ids): array
    {
        if (!$ids) return [];
        $linked=[];
        foreach (Schema::getTables() as $table) {
            $name=$table['name'];
            if ($name==='wmi_years' || !str_starts_with($name,'wmi_') || !Schema::hasColumn($name,'wmi_year_id')) continue;
            $linked=[...$linked,...DB::table($name)->whereIn('wmi_year_id',$ids)->distinct()->pluck('wmi_year_id')->all()];
        }
        return array_values(array_unique(array_map('intval',$linked)));
    }
}
