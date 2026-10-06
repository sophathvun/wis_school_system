<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Student extends Model
{
    protected $table = 'tb_student';
    protected $fillable = ['student_no', 'student_id', 'id_card_qr_code', 'id_card_qr_generated_at', 'photo_path', 'family_number', 'full_name_en', 'full_name_kh', 'gender', 'gender_kh', 'date_of_birth', 'home_phone', 'email', 'nationality_country_id', 'birth_country_id', 'birth_province_id', 'birth_district_id', 'birth_commune_id', 'birth_village_id', 'birth_village_en', 'birth_village_kh', 'birth_commune_en', 'birth_commune_kh', 'birth_district_en', 'birth_district_kh', 'birth_province_en', 'birth_province_kh', 'address_country_id', 'address_province_id', 'address_district_id', 'address_commune_id', 'address_village_id', 'address_house_no_en', 'address_house_no_kh', 'address_street_en', 'address_street_kh', 'current_address_en', 'current_address_kh', 'previous_school', 'experienced_english', 'test_result', 'tested_by', 'remarks', 'status'];
    protected $casts = ['date_of_birth' => 'date:Y-m-d', 'id_card_qr_generated_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (Student $student): void {
            if (! Schema::hasColumn($student->getTable(), 'id_card_qr_code')) {
                return;
            }

            if (blank($student->id_card_qr_code)) {
                $student->id_card_qr_code = static::makeUniqueIdCardQrCode();
                $student->id_card_qr_generated_at = now();
            }
        });
    }

    public function ensureIdCardQrCode(): string
    {
        if (! Schema::hasColumn($this->getTable(), 'id_card_qr_code')) {
            return (string) $this->getKey();
        }

        if (filled($this->id_card_qr_code)) {
            return $this->id_card_qr_code;
        }

        $this->forceFill([
            'id_card_qr_code' => static::makeUniqueIdCardQrCode(),
            'id_card_qr_generated_at' => now(),
        ])->saveQuietly();

        return $this->id_card_qr_code;
    }

    public static function makeUniqueIdCardQrCode(): string
    {
        do {
            $code = 'SID-' . Str::upper(Str::random(24));
        } while (static::query()->where('id_card_qr_code', $code)->exists());

        return $code;
    }

    public function setHomePhoneAttribute($value): void
    {
        $this->attributes['home_phone'] = PhoneNumber::normalize($value);
    }

    public function enrollments() { return $this->hasMany(StudentEnrollment::class); }
    public function families(): BelongsToMany { return $this->belongsToMany(Family::class, 'tb_family_student')->withPivot(['relationship_type', 'is_primary_contact', 'has_pickup_authorization', 'has_portal_access'])->withTimestamps(); }
    public function familyMembers(): BelongsToMany { return $this->belongsToMany(FamilyMember::class, 'tb_student_family_member')->withPivot(['relationship_type', 'is_primary_contact'])->withTimestamps(); }
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
