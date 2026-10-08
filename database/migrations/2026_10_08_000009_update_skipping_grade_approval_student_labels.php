<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceText([
            'label-name'=>['នាមសិស្ស៖','របស់សិស្សឈ្មោះ៖ '],
            'label-target'=>['ស្នើសុំឡើងថ្នាក់៖','ស្នើសុំផ្លោះចូលថ្នាក់ទី៖ '],
            'target-grade'=>['{requested_grade}','{requested_grade_number}'],
        ]);
    }

    public function down(): void
    {
        $this->replaceText([
            'label-name'=>['របស់សិស្សឈ្មោះ៖','នាមសិស្ស៖ '],
            'label-target'=>['ស្នើសុំផ្លោះចូលថ្នាក់ទី៖','ស្នើសុំឡើងថ្នាក់៖ '],
            'target-grade'=>['{requested_grade_number}','{requested_grade}'],
        ]);
    }

    private function replaceText(array $replacements): void
    {
        foreach (DB::table('tb_skipping_grade_settings')->whereNotNull('approval_template')->get(['id','approval_template']) as $setting) {
            $template=json_decode($setting->approval_template,true,512,JSON_THROW_ON_ERROR);
            $changed=false;
            foreach ($replacements as $key=>[$from,$to]) {
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
