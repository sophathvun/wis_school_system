<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceText('− យោងតាមបទបញ្ជាផ្ទៃក្នុងរបស់សាលាវេស្ទើនអន្តរជាតិ។', 'សេចក្តីសម្រេចស្តីពីការបង្កើតគណៈកម្មការវាយសម្លៃសិស្សផ្លោះថ្នាក់');
    }

    public function down(): void
    {
        $this->replaceText('សេចក្តីសម្រេចស្តីពីការបង្កើតគណៈកម្មការវាយសម្លៃសិស្សផ្លោះថ្នាក់', '− យោងតាមបទបញ្ជាផ្ទៃក្នុងរបស់សាលាវេស្ទើនអន្តរជាតិ។');
    }

    private function replaceText(string $from, string $to): void
    {
        foreach (DB::table('tb_skipping_grade_settings')->whereNotNull('approval_template')->get(['id','approval_template']) as $setting) {
            $template=json_decode($setting->approval_template,true,512,JSON_THROW_ON_ERROR);
            $text=$template['reference-rules']['text']??null;
            if (!is_string($text) || trim($text)!==$from) continue;
            $template['reference-rules']['text']=$to;
            DB::table('tb_skipping_grade_settings')->where('id',$setting->id)->update([
                'approval_template'=>json_encode($template,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'updated_at'=>now(),
            ]);
        }
    }
};
