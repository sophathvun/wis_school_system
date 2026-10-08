<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceText('សិស្សត្រូវបំពេញកាតព្វកិច្ចសិក្សាឱ្យបានពេញលេញ។', 'សិស្សត្រូវរកគ្រូបង្រៀនបន្ថែមលើមុខវិជ្ជាដែលខ្សោយ');
    }

    public function down(): void
    {
        $this->replaceText('សិស្សត្រូវរកគ្រូបង្រៀនបន្ថែមលើមុខវិជ្ជាដែលខ្សោយ', 'សិស្សត្រូវបំពេញកាតព្វកិច្ចសិក្សាឱ្យបានពេញលេញ។');
    }

    private function replaceText(string $from, string $to): void
    {
        foreach (DB::table('tb_skipping_grade_settings')->whereNotNull('approval_template')->get(['id','approval_template']) as $setting) {
            $template=json_decode($setting->approval_template,true,512,JSON_THROW_ON_ERROR);
            $text=$template['obligation-study']['text']??null;
            if (!is_string($text) || trim($text)!==$from) continue;
            $template['obligation-study']['text']=$to;
            DB::table('tb_skipping_grade_settings')->where('id',$setting->id)->update([
                'approval_template'=>json_encode($template,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'updated_at'=>now(),
            ]);
        }
    }
};
