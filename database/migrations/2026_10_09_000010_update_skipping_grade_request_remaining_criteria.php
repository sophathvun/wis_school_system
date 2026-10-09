<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_TEXT = [
        'criterion-average-kh'=>'មានពិន្ទុមធ្យមភាគ ៨៥% ឬខ្ពស់ជាងនេះ ក្នុងឆ្នាំសិក្សាមុន។',
        'criterion-average-en'=>'The student maintained an overall average of 85% or higher in the preceding school year.',
        'criterion-documents-kh'=>'មានឯកសារបញ្ជាក់ការបញ្ចប់ថ្នាក់ដែលស្នើសុំរំលង ពីសាលាដើម។',
        'criterion-documents-en'=>'Documents from another school confirm successful completion of the grade to be skipped. For example, Grade 3 must be completed before entering Grade 4.',
        'criterion-recommendation-kh'=>'មានការណែនាំពីគ្រូប្រចាំថ្នាក់ ទាក់ទងនឹងការសិក្សា និងអាកប្បកិរិយា។',
        'criterion-recommendation-en'=>'A recommendation from the class teacher addresses both academic performance and behavior.',
    ];

    private const NEW_TEXT = [
        'criterion-average-kh'=>'ត្រូវមានពិន្ទុមធ្យមភាគ ៨៥% ឡើង (យកពិន្ទុប្រចាំឆ្នាំជាគោល)',
        'criterion-average-en'=>'The student must have maintained an overall average of 85% or higher in the preceding school year.',
        'criterion-documents-kh'=>'មានឯកសារពីសាលាមួយទៀតថាចប់ថ្នាក់ដែលចង់ផ្លោះដោយជោគជ័យ (ឧ. ត្រូវចប់ថ្នាក់ទី៣ មុនចូលថ្នាក់ទី៤)',
        'criterion-documents-en'=>'The student must provide documents from another school confirming the successful completion of the grade they wish to skip. For example, the student must have completed Grade 3 before entering Grade 4.',
        'criterion-recommendation-kh'=>'មានការគាំទ្រពីគ្រូប្រចាំថ្នាក់លើការសិក្សានិងវិន័យ',
        'criterion-recommendation-en'=>'A cecommendation from the class teacher is required., addressing both academic performance and behavior.',
    ];

    public function up(): void
    {
        $this->replaceText(self::OLD_TEXT, self::NEW_TEXT);
    }

    public function down(): void
    {
        $this->replaceText(self::NEW_TEXT, self::OLD_TEXT);
    }

    private function replaceText(array $from, array $to): void
    {
        foreach (DB::table('tb_skipping_grade_settings')->whereNotNull('request_template')->get(['id','request_template']) as $setting) {
            $template=json_decode($setting->request_template,true,512,JSON_THROW_ON_ERROR);
            $changed=false;
            foreach ($from as $key=>$oldText) {
                $text=$template[$key]['text']??null;
                if (!is_string($text) || trim($text)!==$oldText) continue;
                $template[$key]['text']=$to[$key];
                $changed=true;
            }
            if ($changed) DB::table('tb_skipping_grade_settings')->where('id',$setting->id)->update([
                'request_template'=>json_encode($template,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'updated_at'=>now(),
            ]);
        }
    }
};
