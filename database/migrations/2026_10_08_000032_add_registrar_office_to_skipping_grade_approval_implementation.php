<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_TEXT='Receptionists, teachers, the Administration Office, the Accounting Office, and School Principals of all campuses must strictly implement this decision from the date of signature onwards.';
    private const NEW_TEXT="Receptionists, teachers, the Registrar's Office, the Administration Office, the Accounting Office, and School Principals of all campuses must strictly implement this decision from the date of signature onwards.";

    public function up(): void
    {
        $this->replaceText(self::OLD_TEXT, self::NEW_TEXT);
    }

    public function down(): void
    {
        $this->replaceText(self::NEW_TEXT, self::OLD_TEXT);
    }

    private function replaceText(string $from, string $to): void
    {
        foreach (DB::table('tb_skipping_grade_settings')->whereNotNull('approval_template')->get(['id','approval_template']) as $setting) {
            $template=json_decode($setting->approval_template,true,512,JSON_THROW_ON_ERROR);
            $text=$template['implementation-en']['text']??null;
            if (!is_string($text) || trim($text)!==$from) continue;
            $template['implementation-en']['text']=$to;
            DB::table('tb_skipping_grade_settings')->where('id',$setting->id)->update([
                'approval_template'=>json_encode($template,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'updated_at'=>now(),
            ]);
        }
    }
};
