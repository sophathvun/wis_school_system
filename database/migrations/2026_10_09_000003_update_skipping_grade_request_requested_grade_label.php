<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceLabel('ស្នើសុំឡើងថ្នាក់:', 'ស្នើសុំផ្លោះចូលថ្នាក់ទី:');
    }

    public function down(): void
    {
        $this->replaceLabel('ស្នើសុំផ្លោះចូលថ្នាក់ទី:', 'ស្នើសុំឡើងថ្នាក់:');
    }

    private function replaceLabel(string $from, string $to): void
    {
        foreach (DB::table('tb_skipping_grade_settings')->whereNotNull('request_template')->get(['id','request_template']) as $setting) {
            $template=json_decode($setting->request_template,true,512,JSON_THROW_ON_ERROR);
            $text=$template['label-target-kh']['text']??null;
            if (!is_string($text) || trim($text)!==$from) continue;
            $template['label-target-kh']['text']=$to;
            DB::table('tb_skipping_grade_settings')->where('id',$setting->id)->update([
                'request_template'=>json_encode($template,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'updated_at'=>now(),
            ]);
        }
    }
};
