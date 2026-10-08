<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CHANGES = [
        'reference-reviewed'=>['តាមពាក្យស្នើសុំរបស់មាតាបីតាសិស្សនៅថ្ងៃទី {application_date_long_kh}។', 'តាមពាក្យស្នើសុំរបស់មាតាបីតាសិស្សនៅថ្ងៃទី {application_date_long_kh}'],
        'reference-reviewed-en'=>['Grade-Skipping Application submitted by the parents on {application_date}.', 'Grade-Skipping Application submitted by the parents on {application_date}'],
        'reference-received'=>['តាមស្មារតីនៃអង្គប្រជុំរបស់គណៈកម្មការវាយតម្លៃសិស្សផ្លោះថ្នាក់នៅថ្ងៃទី {review_date_long_kh}', 'តាមស្មារតីនៃអង្គប្រជុំរបស់គណៈកម្មការវាយតម្លៃសិស្សផ្លោះថ្នាក់នៅថ្ងៃទី {review_date_long_kh}។'],
        'reference-received-en'=>['Resolution of the Grade-Skipping Evaluation Committee meeting on {review_date_long_en}', 'Resolution of the Grade-Skipping Evaluation Committee meeting on {review_date_long_en}.'],
    ];

    public function up(): void
    {
        $this->replaceText(false);
    }

    public function down(): void
    {
        $this->replaceText(true);
    }

    private function replaceText(bool $reverse): void
    {
        foreach (DB::table('tb_skipping_grade_settings')->whereNotNull('approval_template')->get(['id','approval_template']) as $setting) {
            $template=json_decode($setting->approval_template,true,512,JSON_THROW_ON_ERROR);
            $changed=false;
            foreach (self::CHANGES as $key=>[$old,$new]) {
                $from=$reverse?$new:$old;
                $to=$reverse?$old:$new;
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
