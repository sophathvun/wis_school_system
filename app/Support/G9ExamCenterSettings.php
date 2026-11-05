<?php

namespace App\Support;

class G9ExamCenterSettings
{
    public const DATES=[
        'listDate'=>['label'=>'Candidate List Issue Date','lunar'=>'k_listdate'],
        'resultDate'=>['label'=>'Result Issue Date','lunar'=>'k_resultDate'],
        'resultDateDOEYS'=>['label'=>'DoEYS Result Issue Date','lunar'=>'k_resultDateDOEYS'],
        'cardDate'=>['label'=>'Student Card Issue Date','lunar'=>'k_cardDate'],
        'certDate'=>['label'=>'Certificate Issue Date','lunar'=>'k_certDate'],
    ];

    /** Overlay only explicitly configured center dates; absent dates inherit year defaults. */
    public static function resolve(array $settings,string $center): array
    {
        $config=collect($settings['report_centers']??[])->first(fn($item)=>trim((string)($item['exam_center']??''))===trim($center),[]);
        foreach(self::DATES as $key=>$section){
            if(!isset($config['dates'][$key])||!is_array($config['dates'][$key]))continue;
            $date=$config['dates'][$key];
            $settings[$key.'_iso']=$date['date']??'';
            $settings[$key]=$date['solar']??'';
            $settings[$section['lunar']]=$date['lunar']??'';
        }
        return $settings;
    }
}
