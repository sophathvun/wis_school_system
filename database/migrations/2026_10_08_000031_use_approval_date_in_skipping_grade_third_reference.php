<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TOKENS=[
        'reference-received'=>['{review_date_long_kh}','{approval_date_reference_kh}'],
        'reference-received-en'=>['{review_date_long_en}','{approval_date_reference_en}'],
    ];

    public function up(): void
    {
        $this->replaceTokens(false);
    }

    public function down(): void
    {
        $this->replaceTokens(true);
    }

    private function replaceTokens(bool $reverse): void
    {
        foreach (DB::table('tb_skipping_grade_settings')->whereNotNull('approval_template')->get(['id','approval_template']) as $setting) {
            $template=json_decode($setting->approval_template,true,512,JSON_THROW_ON_ERROR);
            $changed=false;
            foreach (self::TOKENS as $key=>[$old,$new]) {
                $text=$template[$key]['text']??null;
                if (!is_string($text)) continue;
                $replacement=str_replace($reverse?$new:$old,$reverse?$old:$new,$text);
                if ($replacement===$text) continue;
                $template[$key]['text']=$replacement;
                $changed=true;
            }
            if ($changed) DB::table('tb_skipping_grade_settings')->where('id',$setting->id)->update([
                'approval_template'=>json_encode($template,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'updated_at'=>now(),
            ]);
        }
    }
};
