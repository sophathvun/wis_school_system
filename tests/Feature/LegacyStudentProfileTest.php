<?php

use App\Models\Student;
use App\Models\FamilyMember;
use App\Support\LegacyStudentProfile;

it('extracts labelled bilingual house street and village without changing full addresses', function () {
    $en='#168B, Group 1, Ta Ngoun Village, S/K Kakab Khan Por Senchey, Phnom Penh';
    $kh='ផ្ទះលេខ១៦៨EoBo ផ្លូវ២៣K ភូមិតាងួន សង្កាត់កាកាប ខណ្ឌពោធិ៍សែនជ័យ';
    $values=LegacyStudentProfile::address(['home address'=>$en,'kh_current_address'=>$kh]);
    expect($values)->toMatchArray(['current_address_en'=>$en,'current_address_kh'=>$kh,
        'address_house_no_en'=>'168B','address_house_no_kh'=>'១៦៨EoBo','address_street_kh'=>'២៣K',
        'address_village_en'=>'Ta Ngoun','address_village_kh'=>'តាងួន']);
    expect($values)->not->toHaveKey('address_street_en');
    expect(LegacyStudentProfile::addressComponents(['current_address_en'=>'House 1, House 2']))->toBe([]);
    expect(LegacyStudentProfile::addressComponents(['current_address_en'=>'No component labels here']))->toBe([]);
});

it('accepts the actual Province of Birth workbook header and preserves nonempty aliases',function(){
    expect(LegacyStudentProfile::birthplace(['province of birth'=>'Phnom Penh','place of birth'=>'','kh_place_of_birth'=>'ភ្នំពេញ']))->toBe(['birth_province_en'=>'Phnom Penh','birth_province_kh'=>'ភ្នំពេញ']);
    expect(LegacyStudentProfile::birthplace(['province of birth'=>'','place of birth'=>'Kandal']))->toBe(['birth_province_en'=>'Kandal']);
});

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
