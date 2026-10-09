<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceTitle('ពាក្យសុំឡើងថ្នាក់', 'ពាក្យសុំផ្លោះថ្នាក់');
    }

    public function down(): void
    {
        $this->replaceTitle('ពាក្យសុំផ្លោះថ្នាក់', 'ពាក្យសុំឡើងថ្នាក់');
    }

    private function replaceTitle(string $from, string $to): void
    {
        foreach (DB::table('tb_skipping_grade_settings')->whereNotNull('request_template')->get(['id','request_template']) as $setting) {
            $template=json_decode($setting->request_template,true,512,JSON_THROW_ON_ERROR);
            $text=$template['heading-kh']['text']??null;
            if (!is_string($text) || trim($text)!==$from) continue;
            $template['heading-kh']['text']=$to;
            DB::table('tb_skipping_grade_settings')->where('id',$setting->id)->update([
                'request_template'=>json_encode($template,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'updated_at'=>now(),
            ]);
        }
    }
};
