<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model
{
    use SoftDeletes;

    protected $table = 'hrm_staff';

    protected $fillable = [
        'staff_code', 'name_en', 'name_kh', 'gender', 'nationality', 'date_of_birth', 'marital_status', 'phone', 'email', 'current_address',
        'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship', 'photo_path',
        'position_id', 'department_id', 'staff_category', 'employment_type', 'employment_status',
        'joined_on', 'ended_on', 'primary_campus_id', 'notes', 'status', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'date_of_birth' => 'date:Y-m-d',
        'joined_on' => 'date:Y-m-d',
        'ended_on' => 'date:Y-m-d',
        'status' => 'boolean',
    ];

    public function setPhoneAttribute($value): void
    {
        $this->attributes['phone'] = PhoneNumber::normalize($value);
    }

    public function position(): BelongsTo { return $this->belongsTo(Position::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function primaryCampus(): BelongsTo { return $this->belongsTo(SchoolInfo::class, 'primary_campus_id'); }
    public function user(): HasOne { return $this->hasOne(User::class, 'staff_profile_id'); }
    public function experiences(): HasMany { return $this->hasMany(StaffExperience::class, 'staff_id')->orderByDesc('started_on')->orderByDesc('id'); }
    public function educations(): HasMany { return $this->hasMany(StaffEducation::class, 'staff_id')->orderByDesc('started_on')->orderByDesc('id'); }
    public function homeroomAssignments() { return $this->hasMany(HomeroomAssignment::class, 'staff_id'); }

    public function campuses(): BelongsToMany
    {
        return $this->belongsToMany(SchoolInfo::class, 'hrm_staff_campuses', 'staff_id', 'campus_id')
            ->withPivot(['is_primary', 'status', 'assigned_at'])
            ->withTimestamps();
    }

    public function isTeacher(): bool
    {
        return in_array($this->staff_category, ['teacher', 'staff_teacher'], true);
    }
}
