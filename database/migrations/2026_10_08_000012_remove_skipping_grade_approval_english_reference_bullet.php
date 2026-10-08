<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceText('● Decision on the establishment of the Grade-Skipping Evaluation Committee', 'Decision on the establishment of the Grade-Skipping Evaluation Committee');
    }

    public function down(): void
    {
        $this->replaceText('Decision on the establishment of the Grade-Skipping Evaluation Committee', '● Decision on the establishment of the Grade-Skipping Evaluation Committee');
    }

    private function replaceText(string $from, string $to): void
    {
        foreach (DB::table('tb_skipping_grade_settings')->whereNotNull('approval_template')->get(['id','approval_template']) as $setting) {
            $template=json_decode($setting->approval_template,true,512,JSON_THROW_ON_ERROR);
            $text=$template['reference-rules-en']['text']??null;
            if (!is_string($text) || trim($text)!==$from) continue;
            $template['reference-rules-en']['text']=$to;
            DB::table('tb_skipping_grade_settings')->where('id',$setting->id)->update([
                'approval_template'=>json_encode($template,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'updated_at'=>now(),
            ]);
        }
    }
};
