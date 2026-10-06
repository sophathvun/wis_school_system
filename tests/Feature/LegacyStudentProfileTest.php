<?php

use App\Models\Student;
use App\Models\FamilyMember;
use App\Support\LegacyStudentProfile;

it('maps each legacy birthplace level without depending on the sample files at runtime', function () {
    $row=['place of birth'=>'Phnom Penh','kh_place_of_birth'=>'រាជធានីភ្នំពេញ','phoum_pob'=>'Village One','kh_phoum_pob'=>'ភូមិ១','sangkat_pob'=>'Tuek Thla','kh_sangkat_pob'=>'សង្កាត់ទឹកថ្លា','khan_pob'=>'Sen Sok','kh_khan_pob'=>'ខណ្ឌសែនសុខ'];
    $data=LegacyStudentProfile::missingBirthplace(new Student(),$row);
    expect($data)->toHaveCount(8)->and($data['birth_province_en'])->toBe('Phnom Penh')
        ->and($data['birth_village_kh'])->toBe('ភូមិ១')->and($data['birth_commune_en'])->toBe('Tuek Thla')
        ->and($data['birth_district_kh'])->toBe('ខណ្ឌសែនសុខ');
    $student=new Student($data);
    expect(LegacyStudentProfile::missingBirthplace($student,$row))->toBe([]);
});

it('preserves existing birthplace text and structured location selections', function () {
    $student=new Student(['birth_province_id'=>12,'birth_district_en'=>'User entered district']);
    $data=LegacyStudentProfile::missingBirthplace($student,['place of birth'=>'Source province','kh_place_of_birth'=>'ខេត្ត','khan_pob'=>'Source district','kh_khan_pob'=>'ស្រុក','phoum_pob'=>'Village']);
    expect($data)->not->toHaveKeys(['birth_province_en','birth_province_kh','birth_district_en'])
        ->and($data)->toHaveKey('birth_village_en','Village');
});

it('restores unmatched occupation text only for the same family member', function () {
    $member=new FamilyMember(['relationship_type'=>'mother','full_name_en'=>'SAMPLE MOTHER']);
    $row=['mother_name_en'=>'Sample Mother','mother_occupation_en'=>'Unlisted occupation','mother_occupation_kh'=>'មុខរបរគំរូ'];
    expect(LegacyStudentProfile::missingOccupation($member,$row))->toBe(['occupation_en'=>'Unlisted occupation','occupation_kh'=>'មុខរបរគំរូ']);
    $member->occupation_en='Saved occupation';
    expect(LegacyStudentProfile::missingOccupation($member,$row))->toBe(['occupation_kh'=>'មុខរបរគំរូ']);
    $member->occupation_id=3;
    expect(LegacyStudentProfile::missingOccupation($member,$row))->toBe([]);
    $member->occupation_id=null;$member->full_name_en='Different contact';
    expect(LegacyStudentProfile::missingOccupation($member,$row))->toBe([]);
});
