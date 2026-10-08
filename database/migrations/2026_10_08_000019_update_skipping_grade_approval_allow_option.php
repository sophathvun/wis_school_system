<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceText('អនុញ្ញាតឱ្យសិស្សឡើងថ្នាក់ ដោយមានអាយុស្របតាមលក្ខខណ្ឌកំណត់។', 'អនុញ្ញាតឱ្យសិស្សដែលមានឈ្មោះដូចខាងលើផ្លោះចូលថ្នាក់តាមការស្នើសុំ។');
    }

    public function down(): void
    {
        $this->replaceText('អនុញ្ញាតឱ្យសិស្សដែលមានឈ្មោះដូចខាងលើផ្លោះចូលថ្នាក់តាមការស្នើសុំ។', 'អនុញ្ញាតឱ្យសិស្សឡើងថ្នាក់ ដោយមានអាយុស្របតាមលក្ខខណ្ឌកំណត់។');
    }

    private function replaceText(string $from, string $to): void
    {
        foreach (DB::table('tb_skipping_grade_settings')->whereNotNull('approval_template')->get(['id','approval_template']) as $setting) {
            $template=json_decode($setting->approval_template,true,512,JSON_THROW_ON_ERROR);
            $text=$template['age-standard']['text']??null;
            if (!is_string($text) || trim($text)!==$from) continue;
            $template['age-standard']['text']=$to;
            DB::table('tb_skipping_grade_settings')->where('id',$setting->id)->update([
                'approval_template'=>json_encode($template,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'updated_at'=>now(),
            ]);
        }
    }
};
