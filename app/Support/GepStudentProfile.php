<?php
namespace App\Support;

class GepStudentProfile
{
    public static function phone(?string $value): ?string
    {
        $value=trim((string)$value);
        if(str_starts_with($value,'+'))return '+'.preg_replace('/\D/','',$value);
        if(str_starts_with($value,'00'))return '+'.substr(preg_replace('/\D/','',$value),2);
        return PhoneNumber::normalize($value);
    }
    public static function snapshot(\App\Models\Student $student): array
    {
        return self::normalize(array_intersect_key($student->attributesToArray(),array_flip(self::FIELDS)));
    }

    public static function normalize(array $profile): array
    {
        $profile['gender']=match(mb_strtolower(trim($profile['gender']??''))){'m','male','ប្រុស'=>'M','f','female','ស្រី'=>'F',default=>null};
        return $profile;
    }
    public const FIELDS = ['family_number','full_name_en','full_name_kh','gender','gender_kh','date_of_birth','nationality_country_id','home_phone','email',
        'birth_country_id','birth_province_id','birth_district_id','birth_commune_id','birth_village_id',
        'address_country_id','address_province_id','address_district_id','address_commune_id','address_village_id',
        'address_house_no_en','address_house_no_kh','address_street_en','address_street_kh','current_address_en','current_address_kh',
        'previous_school','experienced_english','test_result','tested_by','remarks'];

    public static function rules(): array
    {
        $rules=[];
        foreach(self::FIELDS as $field)$rules[$field]=['nullable','string','max:'.(in_array($field,['remarks','experienced_english','test_result'])?5000:(str_starts_with($field,'current_address')?2000:160))];
        $rules['family_number']=['nullable','string','max:30'];
        $rules['email']=['nullable','email','max:150'];
        $rules['home_phone']=['nullable','string','max:50'];
        $rules['gender']=['nullable','in:M,F'];
        $rules['date_of_birth']=['nullable','date_format:Y-m-d','before_or_equal:today'];
        foreach(['nationality_country_id'=>'country','birth_country_id'=>'country','address_country_id'=>'country',
            'birth_province_id'=>'province','address_province_id'=>'province','birth_district_id'=>'district','address_district_id'=>'district',
            'birth_commune_id'=>'commune','address_commune_id'=>'commune','birth_village_id'=>'village','address_village_id'=>'village'] as $field=>$table)$rules[$field]=['nullable','integer','exists:tb_'.$table.',id'];
        return $rules;
    }
}
