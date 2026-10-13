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
        'place_of_birth_en','place_of_birth_kh','nationality_kh','house_number_en','house_number_kh','street_en','street_kh',
        'village_en','village_kh','commune_en','commune_kh','district_en','district_kh','province_en','province_kh','current_address_kh',
        'employment_end_reason','session_1_in','session_1_out','session_2_in','session_2_out','work_schedule',
        'identity_document_name', 'identity_document_issued_on',
        'secondary_phone', 'work_email', 'identity_document_type', 'identity_document_number', 'identity_document_expires_on',
        'emergency_contact_address', 'contract_type', 'contract_number', 'contract_started_on', 'contract_ends_on',
        'probation_ends_on', 'reports_to_staff_id',
        'birth_country_id', 'birth_province_id',
        'nationality_country_id',
        'address_country_id', 'address_province_id', 'address_district_id', 'address_commune_id', 'address_village_id',
    ];

    protected $casts = [
        'date_of_birth' => 'date:Y-m-d',
        'joined_on' => 'date:Y-m-d',
        'ended_on' => 'date:Y-m-d',
        'status' => 'boolean',
        'work_schedule' => 'array',
        'identity_document_issued_on' => 'date:Y-m-d',
        'identity_document_expires_on' => 'date:Y-m-d',
        'contract_started_on' => 'date:Y-m-d',
        'contract_ends_on' => 'date:Y-m-d',
        'probation_ends_on' => 'date:Y-m-d',
        'birth_country_id' => 'integer',
        'birth_province_id' => 'integer',
        'nationality_country_id' => 'integer',
        'address_country_id' => 'integer', 'address_province_id' => 'integer', 'address_district_id' => 'integer', 'address_commune_id' => 'integer', 'address_village_id' => 'integer',
    ];

    public function setPhoneAttribute($value): void
    {
        $this->attributes['phone'] = $this->normalizeContactPhone($value);
    }

    public function setSecondaryPhoneAttribute($value): void
    {
        $this->attributes['secondary_phone'] = $this->normalizeContactPhone($value);
    }

    public function setEmergencyContactPhoneAttribute($value): void
    {
        $this->attributes['emergency_contact_phone'] = $this->normalizeContactPhone($value);
    }
    private function normalizeContactPhone($value): ?string
    {
        $raw=trim((string)$value);
        if (preg_match('/^\+[0-9\s().-]+$/',$raw)) return '+'.preg_replace('/\D+/','',$raw);
        return PhoneNumber::normalize($value);
    }

    public function position(): BelongsTo { return $this->belongsTo(Position::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function primaryCampus(): BelongsTo { return $this->belongsTo(SchoolInfo::class, 'primary_campus_id'); }
    public function addressCountry(): BelongsTo { return $this->belongsTo(Country::class, 'address_country_id'); }
    public function addressProvince(): BelongsTo { return $this->belongsTo(Province::class, 'address_province_id'); }
    public function addressDistrict(): BelongsTo { return $this->belongsTo(District::class, 'address_district_id'); }
    public function addressCommune(): BelongsTo { return $this->belongsTo(Commune::class, 'address_commune_id'); }
    public function addressVillage(): BelongsTo { return $this->belongsTo(Village::class, 'address_village_id'); }
    public function birthCountry(): BelongsTo { return $this->belongsTo(Country::class, 'birth_country_id'); }
    public function birthProvince(): BelongsTo { return $this->belongsTo(Province::class, 'birth_province_id'); }
    public function nationalityCountry(): BelongsTo { return $this->belongsTo(Country::class, 'nationality_country_id'); }
    public function supervisor(): BelongsTo { return $this->belongsTo(Staff::class, 'reports_to_staff_id'); }
    public function user(): HasOne { return $this->hasOne(User::class, 'staff_profile_id'); }
    public function experiences(): HasMany { return $this->hasMany(StaffExperience::class, 'staff_id')->orderByDesc('started_on')->orderByDesc('id'); }
    public function educations(): HasMany { return $this->hasMany(StaffEducation::class, 'staff_id')->orderByDesc('started_on')->orderByDesc('id'); }
    public function homeroomAssignments() { return $this->hasMany(HomeroomAssignment::class, 'staff_id'); }
    public function workHistories(): HasMany { return $this->hasMany(StaffWorkHistory::class, 'staff_id')->orderByDesc('id'); }
    public function getGenderKhAttribute(): string { return \App\Support\StaffProfile::GENDER_KH[ucfirst(strtolower($this->gender??''))] ?? ''; }
    public function getDateOfBirthKhAttribute(): string { return \App\Support\StaffProfile::dateKhmer($this->date_of_birth); }
    public function getMaritalStatusKhAttribute(): string { return \App\Support\StaffProfile::MARITAL_KH[strtolower($this->marital_status??'')] ?? ''; }
    public function getNationalityNoteAttribute(): string { return \App\Support\StaffProfile::nationalityNote($this->nationality,$this->nationality_kh); }
    public function getEffectiveWorkScheduleAttribute(): array
    {
        $result=[];
        foreach ($this->work_schedule??[] as $day=>$row) {
            $period=$row['period']??'off';
            if ($period==='off') continue;
            foreach ([1,2] as $session) foreach (['in','out'] as $direction) {
                $key='session_'.$session.'_'.$direction;
                $active=$period==='full_day' || ($session===1 && $period==='morning') || ($session===2 && $period==='afternoon');
                $row[$key]=$active?($row[$key]??$this->{$key}):null;
            }
            $result[$day]=$row;
        }
        return $result;
    }

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
