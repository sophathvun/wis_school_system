<?php
namespace App\Support;
class G12NationalExamFields {
    public const GROUPS=[
        'Student information'=>['student_id'=>'Student ID','academic_year'=>'Academic Year','student_name_en'=>'Student Name (English)','student_name_kh'=>'Student Name (Khmer)','gender'=>'Gender','dob'=>'Date of Birth','nationality'=>'Nationality','pob'=>'Place of Birth','current_address'=>'Current Address','grade'=>'Grade / Class','school_name'=>'School Name','campus'=>'Campus','phone_number'=>'Phone Number','photo_filename'=>'MoEYS Photo Filename'],
        'Family information'=>['father_name'=>'Father Name','father_occupation'=>'Father Occupation','mother_name'=>'Mother Name','mother_occupation'=>'Mother Occupation'],
        'Exam details'=>['class_sellected'=>'Stream / Class Selected','language_exam'=>'Exam Language','g9_exam_date'=>'G9 Exam Date','g9_exam_center'=>'G9 Exam Center','g9_exam_room'=>'G9 Room','g9_exam_seat'=>'G9 Seat','g12_exam_date'=>'G12 Exam Date','g12_exam_center'=>'G12 Exam Center','g12_exam_room'=>'G12 Room','g12_exam_seat'=>'G12 Seat'],
        'Results'=>['g12_score_rank'=>'Score / Rank','g12_letter_grade'=>'Overall Letter Grade','g12_lettera_certnumber'=>'Letter A Certificate Number','lettergrade_khmer'=>'Khmer Grade','lettergrade_math'=>'Math Grade','lettergrade_biology'=>'Biology Grade','lettergrade_history'=>'History Grade','lettergrade_chemistry'=>'Chemistry Grade','lettergrade_physics'=>'Physics Grade','lettergrade_english'=>'English Grade','lettergrade_earthscience'=>'Earth Science Grade','lettergrade_geography'=>'Geography Grade','lettergrade_values'=>'Values Grade','remarks'=>'Remarks'],
    ];
    public const DOCUMENT_FIELDS=['soft_certificate'=>'image_soft_cert_name','soft_certificate_received'=>'image_soft_certreceived_name','hard_certificate'=>'image_hard_cert_name','hard_certificate_received'=>'inage_hard_certreceived_name'];
    public static function labels(): array {return array_merge(...array_values(self::GROUPS));}
    public static function filenameKey(string $value): string {return mb_strtolower(pathinfo(str_replace('\\','/',$value),PATHINFO_FILENAME));}
    public static function clean(string $value): string {return trim(preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u','',$value));}
    public static function centerKey(string $value): string {return mb_strtolower(preg_replace('/\s+/u',' ',self::clean($value)));}
}
