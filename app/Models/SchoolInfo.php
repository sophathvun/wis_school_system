<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolInfo extends Model
{
    use SoftDeletes;
    protected $table = 'tb_school_info';

    protected $fillable = [
        'school_name_en',
        'school_name_kh',
        'logo_path',
        'campus_name_en',
        'campus_name_kh',
        'address',
        'address_country_id',
        'address_province_id',
        'address_district_id',
        'address_commune_id',
        'address_village_id',
        'address_en',
        'address_kh',
        'address_house_no_en',
        'address_house_no_kh',
        'address_street_en',
        'address_street_kh',
        'google_map_url',
        'phone',
        'description',
        'status',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'access_user_campuses', 'campus_id', 'user_id')
            ->withPivot(['is_primary', 'assigned_at'])
            ->withTimestamps();
    }
}
