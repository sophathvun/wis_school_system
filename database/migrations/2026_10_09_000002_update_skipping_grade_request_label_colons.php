<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const LABELS = [
        'label-name-kh'=>'ឈ្មោះសិស្ស', 'label-id-kh'=>'អត្តលេខសិស្ស',
        'label-current-kh'=>'ថ្នាក់បច្ចុប្បន្ន', 'label-target-kh'=>'ស្នើសុំឡើងថ្នាក់',
        'label-year-kh'=>'ឆ្នាំសិក្សា', 'label-dob-kh'=>'ថ្ងៃខែឆ្នាំកំណើត',
        'label-campus-kh'=>'សាខា', 'label-parent-kh'=>'មាតាបិតា/អាណាព្យាបាល',
        'label-signature-kh'=>'ហត្ថលេខា', 'label-phone-kh'=>'លេខទូរស័ព្ទ', 'label-date-kh'=>'ថ្ងៃធ្វើពាក្យ',
    ];

    public function up(): void
    {
        $this->replaceColons('៖', ':');
    }

    public function down(): void
    {
        $this->replaceColons(':', '៖');
    }

    private function replaceColons(string $from, string $to): void
    {
        foreach (DB::table('tb_skipping_grade_settings')->whereNotNull('request_template')->get(['id','request_template']) as $setting) {
            $template=json_decode($setting->request_template,true,512,JSON_THROW_ON_ERROR);
            $changed=false;
            foreach (self::LABELS as $key=>$label) {
                $text=$template[$key]['text']??null;
                if (!is_string($text) || trim($text)!==$label.$from) continue;
                $template[$key]['text']=$label.$to;
                $changed=true;
            }
            if ($changed) DB::table('tb_skipping_grade_settings')->where('id',$setting->id)->update([
                'request_template'=>json_encode($template,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'updated_at'=>now(),
            ]);
        }
    }
};
