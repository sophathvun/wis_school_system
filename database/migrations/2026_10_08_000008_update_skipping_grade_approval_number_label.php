<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceLabel('លេខ៖ {reference_number}','លេខ/Nº : {reference_number}');
    }

    public function down(): void
    {
        $this->replaceLabel('លេខ/Nº : {reference_number}','លេខ៖ {reference_number}');
    }

    private function replaceLabel(string $from, string $to): void
    {
        foreach (DB::table('tb_skipping_grade_settings')->whereNotNull('approval_template')->get(['id','approval_template']) as $setting) {
            $template=json_decode($setting->approval_template,true,512,JSON_THROW_ON_ERROR);
            $text=$template['reference']['text']??null;
            if (!is_string($text) || trim($text)!==$from) continue;
            $template['reference']['text']=$to;
            DB::table('tb_skipping_grade_settings')->where('id',$setting->id)->update([
                'approval_template'=>json_encode($template,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'updated_at'=>now(),
            ]);
        }
    }
};
