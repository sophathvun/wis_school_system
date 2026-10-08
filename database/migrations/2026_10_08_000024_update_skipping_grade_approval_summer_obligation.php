<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceText('សិស្សត្រូវមានអាកប្បកិរិយាល្អ។', 'សិស្សត្រូវចូលរៀនវគ្គបំប៉នវិស្សមកាល');
    }

    public function down(): void
    {
        $this->replaceText('សិស្សត្រូវចូលរៀនវគ្គបំប៉នវិស្សមកាល', 'សិស្សត្រូវមានអាកប្បកិរិយាល្អ។');
    }

    private function replaceText(string $from, string $to): void
    {
        foreach (DB::table('tb_skipping_grade_settings')->whereNotNull('approval_template')->get(['id','approval_template']) as $setting) {
            $template=json_decode($setting->approval_template,true,512,JSON_THROW_ON_ERROR);
            $text=$template['obligation-conduct']['text']??null;
            if (!is_string($text) || trim($text)!==$from) continue;
            $template['obligation-conduct']['text']=$to;
            DB::table('tb_skipping_grade_settings')->where('id',$setting->id)->update([
                'approval_template'=>json_encode($template,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'updated_at'=>now(),
            ]);
        }
    }
};
