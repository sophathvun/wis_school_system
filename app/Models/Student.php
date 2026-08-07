<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $table = 'tb_student';
    protected $fillable = ['student_no', 'student_id', 'photo_path', 'family_number', 'full_name_en', 'full_name_kh', 'first_name_en', 'last_name_en', 'first_name_kh', 'last_name_kh', 'gender', 'gender_kh', 'date_of_birth', 'home_phone', 'email', 'nationality_country_id', 'birth_country_id', 'birth_province_id', 'birth_district_id', 'birth_commune_id', 'birth_village_id', 'address_country_id', 'address_province_id', 'address_district_id', 'address_commune_id', 'address_village_id', 'address_house_no_en', 'address_house_no_kh', 'address_street_en', 'address_street_kh', 'current_address_en', 'current_address_kh', 'previous_school', 'experienced_english', 'test_result', 'tested_by', 'remarks', 'status'];
    protected $casts = ['date_of_birth' => 'date:Y-m-d'];

    public function enrollments() { return $this->hasMany(StudentEnrollment::class); }
    public function families(): BelongsToMany { return $this->belongsToMany(Family::class, 'tb_family_student')->withPivot(['relationship_type', 'is_primary_contact', 'has_pickup_authorization', 'has_portal_access'])->withTimestamps(); }
    public function contacts(): HasMany { return $this->hasMany(StudentContact::class); }
    public function addresses(): HasMany { return $this->hasMany(StudentAddress::class); }
    public function documents(): HasMany { return $this->hasMany(StudentDocument::class); }
    public function birthCountry() { return $this->belongsTo(Country::class, 'birth_country_id'); }
    public function birthProvince() { return $this->belongsTo(Province::class, 'birth_province_id'); }
    public function birthDistrict() { return $this->belongsTo(District::class, 'birth_district_id'); }
    public function birthCommune() { return $this->belongsTo(Commune::class, 'birth_commune_id'); }
    public function birthVillage() { return $this->belongsTo(Village::class, 'birth_village_id'); }
    public function nationalityCountry() { return $this->belongsTo(Country::class, 'nationality_country_id'); }
    public function addressCountry() { return $this->belongsTo(Country::class, 'address_country_id'); }
    public function addressProvince() { return $this->belongsTo(Province::class, 'address_province_id'); }
    public function addressDistrict() { return $this->belongsTo(District::class, 'address_district_id'); }
    public function addressCommune() { return $this->belongsTo(Commune::class, 'address_commune_id'); }
    public function addressVillage() { return $this->belongsTo(Village::class, 'address_village_id'); }
}
