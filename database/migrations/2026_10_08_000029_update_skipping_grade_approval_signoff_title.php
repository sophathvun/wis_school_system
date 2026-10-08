<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceText(['{signer_title_kh}', 'អនុប្រធាន'], 'ប្រធានគណៈកម្មការ');
    }

    public function down(): void
    {
        $this->replaceText(['ប្រធានគណៈកម្មការ'], '{signer_title_kh}');
    }

    private function replaceText(array $from, string $to): void
    {
        foreach (DB::table('tb_skipping_grade_settings')->whereNotNull('approval_template')->get(['id','approval_template']) as $setting) {
            $template=json_decode($setting->approval_template,true,512,JSON_THROW_ON_ERROR);
            $text=$template['signoff-title']['text']??null;
            if (!is_string($text) || !in_array(trim($text),$from,true)) continue;
            $template['signoff-title']['text']=$to;
            DB::table('tb_skipping_grade_settings')->where('id',$setting->id)->update([
                'approval_template'=>json_encode($template,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'updated_at'=>now(),
            ]);
        }
    }
};
