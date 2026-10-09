<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD_TEXT='ត្រូវមានអាយុត្រឹមត្រូវតាមច្បាប់រដ្ឋ (មានសេចក្តីចម្លងសំបុត្រកំណើតច្បាប់ដើមធាភស្តុតាង)';
    private const NEW_TEXT='ត្រូវមានអាយុត្រឹមត្រូវតាមច្បាប់រដ្ឋ (មានសេចក្តីចម្លងសំបុត្រកំណើតច្បាប់ដើមជាភស្តុតាង)';

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
        foreach (DB::table('tb_skipping_grade_settings')->whereNotNull('request_template')->get(['id','request_template']) as $setting) {
            $template=json_decode($setting->request_template,true,512,JSON_THROW_ON_ERROR);
            $text=$template['criterion-age-kh']['text']??null;
            if (!is_string($text) || trim($text)!==$from) continue;
            $template['criterion-age-kh']['text']=$to;
            DB::table('tb_skipping_grade_settings')->where('id',$setting->id)->update([
                'request_template'=>json_encode($template,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'updated_at'=>now(),
            ]);
        }
    }
};
