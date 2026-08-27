<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FamilyMember extends Model
{
    use SoftDeletes;

    public const RELATIONSHIP_MOTHER = 'mother';
    public const RELATIONSHIP_FATHER = 'father';
    public const RELATIONSHIP_GUARDIAN = 'guardian';

    public const RELATIONSHIP_TYPES = [
        self::RELATIONSHIP_MOTHER,
        self::RELATIONSHIP_FATHER,
        self::RELATIONSHIP_GUARDIAN,
    ];

    protected $table = 'tb_family_member';

    protected $fillable = ['family_id', 'user_id', 'full_name_en', 'full_name_kh', 'relationship_type', 'phone', 'email', 'occupation', 'occupation_en', 'occupation_kh', 'occupation_id', 'workplace', 'nationality_en', 'nationality_kh', 'nationality_country_id', 'nationality_id', 'is_primary_contact', 'has_pickup_authorization', 'has_portal_access', 'status'];

    protected function casts(): array
    {
        return ['is_primary_contact' => 'boolean', 'has_pickup_authorization' => 'boolean', 'has_portal_access' => 'boolean', 'status' => 'integer'];
    }

    public function setPhoneAttribute($value): void
    {
        $this->attributes['phone'] = PhoneNumber::normalize($value);
    }

    public function family(): BelongsTo { return $this->belongsTo(Family::class); }
    public function students() { return $this->belongsToMany(Student::class, 'tb_student_family_member')->withPivot(['relationship_type', 'is_primary_contact'])->withTimestamps(); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
