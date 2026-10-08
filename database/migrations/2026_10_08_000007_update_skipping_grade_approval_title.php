<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceTitle('សេចក្តីសម្រេច ស្តីពី ការអនុញ្ញាតឱ្យសិស្សឡើងថ្នាក់','សេចក្តីសម្រេច ស្តីពី ការវាយតម្លៃសិស្សសុំផ្លោះថ្នាក់');
    }

    public function down(): void
    {
        $this->replaceTitle('សេចក្តីសម្រេច ស្តីពី ការវាយតម្លៃសិស្សសុំផ្លោះថ្នាក់','សេចក្តីសម្រេច ស្តីពី ការអនុញ្ញាតឱ្យសិស្សឡើងថ្នាក់');
    }

    private function replaceTitle(string $from, string $to): void
    {
        foreach (DB::table('tb_skipping_grade_settings')->whereNotNull('approval_template')->get(['id','approval_template']) as $setting) {
            $template=json_decode($setting->approval_template,true,512,JSON_THROW_ON_ERROR);
            $text=$template['heading']['text']??null;
            if (!is_string($text) || preg_replace('/\s+/u',' ',trim($text))!==$from) continue;
            $template['heading']['text']=$to;
            DB::table('tb_skipping_grade_settings')->where('id',$setting->id)->update([
                'approval_template'=>json_encode($template,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'updated_at'=>now(),
            ]);
        }
    }
};
