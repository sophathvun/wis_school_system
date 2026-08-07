<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Family extends Model
{
    use SoftDeletes;

    protected $table = 'tb_family';

    protected $fillable = ['family_number', 'family_name', 'family_name_kh', 'primary_phone', 'primary_email', 'address', 'status'];

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'tb_family_student', 'family_id', 'student_id')
            ->withPivot(['relationship_type', 'is_primary_contact', 'has_pickup_authorization', 'has_portal_access'])
            ->withTimestamps();
    }

    public function members(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }
}
