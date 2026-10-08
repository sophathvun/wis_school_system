<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TEXTS = [
        'reference-reviewed'=>['តាមពាក្យស្នើសុំរបស់មាតាបីតាសិស្សនៅថ្ងៃទី {application_date_long_kh}', '។'],
        'reference-reviewed-en'=>['Grade-Skipping Application submitted by the parents on {application_date}', '.'],
    ];

    public function up(): void
    {
        $this->replaceText(true);
    }

    public function down(): void
    {
        $this->replaceText(false);
    }

    private function replaceText(bool $append): void
    {
        foreach (DB::table('tb_skipping_grade_settings')->whereNotNull('approval_template')->get(['id','approval_template']) as $setting) {
            $template=json_decode($setting->approval_template,true,512,JSON_THROW_ON_ERROR);
            $changed=false;
            foreach (self::TEXTS as $key=>[$original,$punctuation]) {
                $from=$append?$original:$original.$punctuation;
                $to=$append?$original.$punctuation:$original;
                $text=$template[$key]['text']??null;
                if (!is_string($text) || trim($text)!==$from) continue;
                $template[$key]['text']=$to;
                $changed=true;
            }
            if ($changed) DB::table('tb_skipping_grade_settings')->where('id',$setting->id)->update([
                'approval_template'=>json_encode($template,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'updated_at'=>now(),
            ]);
        }
    }
};
