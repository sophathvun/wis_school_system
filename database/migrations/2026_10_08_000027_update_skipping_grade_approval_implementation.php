<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_TEXT='គណៈគ្រប់គ្រងសាខា គ្រូប្រចាំថ្នាក់ មាតាបិតា ឬអាណាព្យាបាល និងសិស្ស ត្រូវចូលរួមអនុវត្តសេចក្តីសម្រេចនេះ។ ការឡើងថ្នាក់មានប្រសិទ្ធភាពចាប់ពីថ្ងៃទី {effective_date_kh} សម្រាប់ឆ្នាំសិក្សា {requested_academic_year}។';
    private const NEW_TEXT='បុគ្គលិកទទួលព័ត៌មាន លោកគ្រូអ្នកគ្រូ ការិយាល័យសិក្សា ការិយាល័យរដ្ឋបាល ការិយាល័យគណនេយ្យ នាយកសាខាគ្រប់សាខាទាំងអស់ត្រូវគោរពតាមខ្លឹមសារនៃសេចក្តីសម្រេចនេះឱ្យមានប្រសិទ្ធិភាពចាប់ពីថ្ងៃចុះហត្ថលេខានេះតទៅ។';

    public function up(): void
    {
        $this->replaceText(self::OLD_TEXT, self::NEW_TEXT);
    }

    public function down(): void
    {
        $this->replaceText(self::NEW_TEXT, self::OLD_TEXT);
    }

    private function replaceText(string $from, string $to): void
    {
        foreach (DB::table('tb_skipping_grade_settings')->whereNotNull('approval_template')->get(['id','approval_template']) as $setting) {
            $template=json_decode($setting->approval_template,true,512,JSON_THROW_ON_ERROR);
            $text=$template['implementation']['text']??null;
            if (!is_string($text) || trim($text)!==$from) continue;
            $template['implementation']['text']=$to;
            DB::table('tb_skipping_grade_settings')->where('id',$setting->id)->update([
                'approval_template'=>json_encode($template,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'updated_at'=>now(),
            ]);
        }
    }
};
