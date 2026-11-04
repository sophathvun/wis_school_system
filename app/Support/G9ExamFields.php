<?php

namespace App\Support;

class G9ExamFields
{
    public const GROUPS = [
        'Student Information' => [
            'academic_year_id'=>'Academic Year', 'student_code'=>'Student ID', 'name_kh'=>'Student Name Khmer',
            'name_en'=>'Student Name English', 'gender'=>'Gender', 'nationality'=>'Nationality', 'ethnicity'=>'Ethnicity',
            'date_of_birth'=>'Date of Birth', 'place_of_birth'=>'Place of Birth', 'foreign_language'=>'Foreign Language',
        ],
        'Family Information' => ['father_name'=>'Father Name','father_occupation'=>'Father Occupation','mother_name'=>'Mother Name','mother_occupation'=>'Mother Occupation'],
        'Examination Details' => ['exam_center'=>'Exam Center','exam_date'=>'Exam Date','room_number'=>'Room Number','seat_number'=>'Seat Number','exam_language'=>'Exam Language'],
        'School Information' => ['school_name'=>'School Name','campus_id'=>'Campus','grade'=>'Grade','phone_number'=>'Phone Number','photo_filename'=>'MoEYS Photo Filename'],
        'School Scores' => ['semester_1'=>'Score Semester 1','semester_2'=>'Score Semester 2','eoy_average'=>'EOY Average'],
        'National Examination Scores' => [
            'score_khmer_essay'=>'Exam Score Khmer Essay','score_chemistry'=>'Exam Score Chemistry',
            'score_earth_science'=>'Exam Score Earth Science','score_physics'=>'Exam Score Physics',
            'score_geography'=>'Exam Score Geography','score_values'=>'Exam Score Values','score_math'=>'Exam Score Math',
            'score_khmer_dictation'=>'Exam Score Khmer Dictation','score_biology'=>'Exam Score Biology',
            'score_history'=>'Exam Score History','score_english'=>'Exam Score English',
        ],
    ];

    public static function rules(): array
    {
        $rules=[];
        foreach(self::GROUPS as $fields) foreach($fields as $key=>$label) {
            $rules[$key] = str_starts_with($key,'score_') || in_array($key,['semester_1','semester_2','eoy_average'],true)
                ? ['nullable','numeric','min:0','max:9999.999'] : ['nullable','string','max:255'];
        }
        return array_replace($rules,[
            'academic_year_id'=>['required','integer','exists:tb_academic_year,id'],
            'campus_id'=>['required','integer','exists:tb_school_info,id'],
            'student_code'=>['required','string','max:80'],
            'name_kh'=>['nullable','string','max:255'], 'name_en'=>['required','string','max:255'],
            'date_of_birth'=>['nullable','date_format:Y-m-d','before_or_equal:today'],
            'exam_date'=>['nullable','date_format:Y-m-d'],
            'version'=>['nullable','integer','min:1'],
            'photo'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:5120'],
        ]);
    }
}
