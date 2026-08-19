<?php

namespace App\Services;

use App\Models\Family;
use App\Models\Student;
use App\Models\FamilyMember;
use App\Models\Country;
use App\Models\Occupation;

class FamilyService
{
    public function syncStudentFamily(Student $student, string $familyNumber): Family
    {
        $family = Family::withTrashed()->firstOrCreate(
            ['family_number' => $familyNumber],
            ['family_name' => $familyNumber, 'status' => 1],
        );

        if ($family->trashed()) {
            $family->restore();
        }

        $family->students()->syncWithoutDetaching([$student->id]);
        return $family;
    }

    public function syncEnrollmentMember(Family $family, string $relationshipType, array $data): ?FamilyMember
    {
        $hasData = collect($data)->contains(fn ($value) => filled($value));
        if (!$hasData) {
            return null;
        }

        $member = $family->members()->where('relationship_type', $relationshipType)->first()
            ?? new FamilyMember(['family_id' => $family->id, 'relationship_type' => $relationshipType]);

        $fullNameEn = $data['full_name_en'] ?? null;
        $fullNameKh = $data['full_name_kh'] ?? null;
        $member->fill([
            'full_name_en' => $fullNameEn,
            'full_name_kh' => $fullNameKh,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'occupation_en' => $data['occupation_en'] ?? ($data['occupation'] ?? null),
            'occupation_kh' => $data['occupation_kh'] ?? null,
            'workplace' => $data['workplace'] ?? null,
            'nationality_en' => $data['nationality_en'] ?? null,
            'nationality_kh' => $data['nationality_kh'] ?? null,
            'nationality_id' => null,
            'occupation_id' => $data['occupation_id'] ?? null,
            'is_primary_contact' => $data['is_primary_contact'] ?? false,
            'has_pickup_authorization' => $data['has_pickup_authorization'] ?? false,
            'has_portal_access' => $data['has_portal_access'] ?? false,
            'status' => 1,
        ])->save();

        if (!empty($data['nationality_country_id'])) {
            $country = Country::find($data['nationality_country_id']);
            $member->update(['nationality_en' => $country?->nationality_name_en, 'nationality_kh' => $country?->nationality_name_kh, 'nationality_country_id' => $country?->id]);
        }
        if (!empty($data['occupation_id'])) {
            $occupation = Occupation::find($data['occupation_id']);
            $member->update(['occupation_en' => $occupation?->occupation_name_en, 'occupation_kh' => $occupation?->occupation_name_kh, 'occupation' => $occupation?->occupation_name_en]);
        }

        return $member;
    }
}
