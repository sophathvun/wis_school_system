<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceHeading('លក្ខខណ្ឌសម្រាប់សិស្សសុំឡើងថ្នាក់', 'លក្ខណៈវិនិច្ឆ័យ');
    }

    public function down(): void
    {
        $this->replaceHeading('លក្ខណៈវិនិច្ឆ័យ', 'លក្ខខណ្ឌសម្រាប់សិស្សសុំឡើងថ្នាក់');
    }

    private function replaceHeading(string $from, string $to): void
    {
        foreach (DB::table('tb_skipping_grade_settings')->whereNotNull('request_template')->get(['id','request_template']) as $setting) {
            $template=json_decode($setting->request_template,true,512,JSON_THROW_ON_ERROR);
            $text=$template['criteria-heading-kh']['text']??null;
            if (!is_string($text) || trim($text)!==$from) continue;
            $template['criteria-heading-kh']['text']=$to;
            DB::table('tb_skipping_grade_settings')->where('id',$setting->id)->update([
                'request_template'=>json_encode($template,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'updated_at'=>now(),
            ]);
        }
    }
};
