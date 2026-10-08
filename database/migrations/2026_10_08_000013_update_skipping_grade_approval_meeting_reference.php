<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceText('− យោងតាមពាក្យស្នើសុំរបស់មាតាបិតា ឬអាណាព្យាបាលសិស្ស ដែលបានទទួលនៅថ្ងៃទី {received_date_kh}។', 'តាមស្មារតីនៃអង្គប្រជុំរបស់គណៈកម្មការវាយតម្លៃសិស្សផ្លោះថ្នាក់នៅថ្ងៃទី {review_date_long_kh}');
    }

    public function down(): void
    {
        $this->replaceText('តាមស្មារតីនៃអង្គប្រជុំរបស់គណៈកម្មការវាយតម្លៃសិស្សផ្លោះថ្នាក់នៅថ្ងៃទី {review_date_long_kh}', '− យោងតាមពាក្យស្នើសុំរបស់មាតាបិតា ឬអាណាព្យាបាលសិស្ស ដែលបានទទួលនៅថ្ងៃទី {received_date_kh}។');
    }

    private function replaceText(string $from, string $to): void
    {
        foreach (DB::table('tb_skipping_grade_settings')->whereNotNull('approval_template')->get(['id','approval_template']) as $setting) {
            $template=json_decode($setting->approval_template,true,512,JSON_THROW_ON_ERROR);
            $text=$template['reference-received']['text']??null;
            if (!is_string($text) || trim($text)!==$from) continue;
            $template['reference-received']['text']=$to;
            DB::table('tb_skipping_grade_settings')->where('id',$setting->id)->update([
                'approval_template'=>json_encode($template,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'updated_at'=>now(),
            ]);
        }
    }
};
