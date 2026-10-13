<?php

namespace App\Support;

class StaffDataColumns
{
    public const FIELDS = [
        'staff_code'=>'Staff ID','name_en'=>'Staff Name (English)','name_kh'=>'Staff Name (Khmer)',
        'gender'=>'Gender (English)','gender_kh'=>'Gender (Khmer)','date_of_birth'=>'Date of Birth (English)','date_of_birth_kh'=>'Date of Birth (Khmer)',
        'birth_country_en'=>'Country of Birth (English)','birth_country_kh'=>'Country of Birth (Khmer)',
        'place_of_birth_en'=>'Province/City of Birth (English)','place_of_birth_kh'=>'Province/City of Birth (Khmer)',
        'nationality'=>'Nationality (English)','nationality_kh'=>'Nationality (Khmer)','nationality_note'=>'Nationality Note',
        'marital_status'=>'Marital Status (English)','marital_status_kh'=>'Marital Status (Khmer)',
        'phone'=>'Phone Number','secondary_phone'=>'Alternative Phone Number','email'=>'Email','work_email'=>'Work Email',
        'address_country_en'=>'Address Country (English)','address_country_kh'=>'Address Country (Khmer)',
        'province_en'=>'Address Province/City (English)','province_kh'=>'Address Province/City (Khmer)',
        'district_en'=>'Address District/Khan (English)','district_kh'=>'Address District/Khan (Khmer)',
        'commune_en'=>'Address Commune/Sangkat (English)','commune_kh'=>'Address Commune/Sangkat (Khmer)',
        'village_en'=>'Address Village (English)','village_kh'=>'Address Village (Khmer)',
        'house_number_en'=>'House Number (English)','house_number_kh'=>'House Number (Khmer)','street_en'=>'Street (English)','street_kh'=>'Street (Khmer)',
        'current_address'=>'Current Address (English)','current_address_kh'=>'Current Address (Khmer)',
        'identity_document_type'=>'Document Type','identity_document_name'=>'Document Name','identity_document_number'=>'Document Number',
        'identity_document_issued_on'=>'Document Issue Date','identity_document_expires_on'=>'Document Expiry Date',
        'emergency_contact_name'=>'Emergency Contact Name','emergency_contact_phone'=>'Emergency Contact Phone',
        'emergency_contact_relationship'=>'Emergency Contact Relationship','emergency_contact_address'=>'Emergency Contact Address',
        'joined_on'=>'Hire Date','position'=>'Position','department'=>'Department','supervisor_code'=>'Supervisor Staff ID',
        'primary_campus'=>'Primary Campus','working_campuses'=>'Working Campuses','staff_category'=>'Staff Category',
        'employment_type'=>'Employment Type','employment_status'=>'Employment Status','ended_on'=>'Resignation / Termination / Retirement Date',
        'employment_end_reason'=>'Leaving Reason','status'=>'Profile Status','contract_type'=>'Contract Type','contract_number'=>'Contract Number',
        'contract_started_on'=>'Contract Start Date','contract_ends_on'=>'Contract End Date','probation_ends_on'=>'Probation End Date',
        'session_1_in'=>'Session 1 Time In','session_1_out'=>'Session 1 Time Out','session_2_in'=>'Session 2 Time In','session_2_out'=>'Session 2 Time Out',
        'notes'=>'Remarks',
    ];
    public const EDUCATION = ['degree'=>'Degree','field_of_study'=>'Major','institution_name'=>'Institution','country'=>'Country','graduated_year'=>'Graduation Year','started_on'=>'From','ended_on'=>'To','grade_or_result'=>'Result','notes'=>'Remarks'];
    public const EMPLOYMENT = ['company_name'=>'Employer','position'=>'Position','started_on'=>'From','ended_on'=>'To','employment_type'=>'Employment Type','is_current'=>'Current Employment','responsibilities'=>'Remarks'];
    public const CATEGORIES = ['staff'=>'Staff','teacher'=>'Teacher','staff_teacher'=>'Staff & Teacher'];
    public const TYPES = ['PT'=>'Part-Time','SFT'=>'Special Full-Time','FT'=>'Full-Time'];

    public static function fields(int $educationCount=1,int $employmentCount=1): array
    {
        $fields=self::FIELDS;
        foreach(StaffProfile::DAYS as $day=>$label) {
            $fields['work_schedule.'.$day.'.period']=$label.' Working Period';
            foreach([1,2] as $session) foreach(['in','out'] as $direction) $fields['work_schedule.'.$day.'.session_'.$session.'_'.$direction]=$label.' Session '.$session.' Time '.ucfirst($direction);
        }
        foreach(['educations'=>[$educationCount,self::EDUCATION,'Education'],'experiences'=>[$employmentCount,self::EMPLOYMENT,'Previous Employment']] as $section=>[$count,$columns,$label]) {
            for($index=0;$index<$count;$index++) foreach($columns as $field=>$heading) $fields[$section.'.'.$index.'.'.$field]=$label.' '.($index+1).' '.$heading;
        }
        return $fields;
    }

    public static function enumLabels(): array
    {
        return ['gender'=>['Male'=>'Male','Female'=>'Female','Other'=>'Other'],'marital_status'=>StaffProfile::MARITAL,
            'staff_category'=>self::CATEGORIES,'employment_type'=>self::TYPES,'employment_status'=>StaffProfile::STATUS,
            'contract_type'=>StaffProfile::CONTRACTS,'identity_document_type'=>StaffProfile::IDENTITY_DOCUMENTS,
            'status'=>['1'=>'Active','0'=>'Inactive']];
    }
}
