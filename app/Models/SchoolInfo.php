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
