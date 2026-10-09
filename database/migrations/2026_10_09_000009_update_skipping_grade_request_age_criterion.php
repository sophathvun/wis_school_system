<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_TEXT = [
        'criterion-age-kh'=>'ត្រូវមានអាយុស្របតាមថ្នាក់ ដោយមានសំបុត្រកំណើតដើមជាភស្តុតាង។',
        'criterion-age-en'=>'The student meets the legal age requirement for the requested grade, supported by the original birth certificate (for example, 6 years old for Grade 1).',
    ];

    private const NEW_TEXT = [
        'criterion-age-kh'=>'ត្រូវមានអាយុត្រឹមត្រូវតាមច្បាប់រដ្ឋ (មានសេចក្តីចម្លងសំបុត្រកំណើតច្បាប់ដើមធាភស្តុតាង)',
        'criterion-age-en'=>'The student must meet the legal requirement for the grade based on the original cophy of their birth certificate as proof from the district authorities. (e.g. 6 years old for Grade 1).',
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
