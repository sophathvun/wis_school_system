<?php

namespace App\Services;

use App\Models\Family;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class FamilyStudentLinks
{
    public function refreshStudentMembers(Student $student): void
    {
        $members = $student->families()->with('members')->get()->flatMap->members;
        $student->familyMembers()->sync($members->mapWithKeys(fn ($member) => [$member->id => [
            'relationship_type' => $member->relationship_type, 'is_primary_contact' => $member->is_primary_contact,
        ]])->all());
    }

    public function refreshFamilyMembers(Family $family): void
    {
        foreach ($family->students()->get() as $student) $this->refreshStudentMembers($student);
    }

    public function hasStudents(Family $family): bool
    {
        return $family->students()->exists()
            || Student::where('family_number', $family->family_number)->exists()
            || DB::table('tb_student_family_member as link')->join('tb_family_member as member', 'member.id', '=', 'link.family_member_id')
                ->where('member.family_id', $family->id)->exists();
    }
}
