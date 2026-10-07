<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class K3CertificateLayout
{
    public static function defaults(?array $typography = null): array
    {
        $styles = K3CertificateTypography::resolve($typography);
        $content = [
            'class_campus'=>['text'=>'K3-{{class}} / {{campus}}','x'=>77,'y'=>4,'width'=>20],
            'heading'=>['text'=>'This certifies that','x'=>10,'y'=>43,'width'=>80,'bold'=>true],
            'student_name'=>['text'=>'{{student_name}}','x'=>14,'y'=>47.6,'width'=>72],
            'completion'=>['text'=>'has successfully completed Kindergarten at Western International School.','x'=>10,'y'=>53.1,'width'=>80],
            'given_date'=>['text'=>'Given in Phnom Penh on the {{given_day}} day of {{given_month}} {{given_year}}','x'=>10,'y'=>56.7,'width'=>80],
            'chairman'=>['text'=>"H.E. Laurent Te\nChairman",'x'=>17,'y'=>76,'width'=>27,'bold_first_line'=>true],
            'director'=>['text'=>"Teena Marie Estioko\nInternational Program Director",'x'=>56,'y'=>76,'width'=>30,'bold_first_line'=>true],
            'number'=>['text'=>'Nº:{{certificate_number}}','x'=>42,'y'=>82.4,'width'=>17],
        ];
        $fields = [];
        foreach ($content as $key=>$field) $fields[$key] = $field + $styles[$key] + ['bold'=>false,'bold_first_line'=>false,'align'=>'center'];
        $fields['photo'] = ['x'=>46.9,'y'=>68.4,'width'=>7.5,'height'=>12.4];
        $fields['qr'] = ['x'=>33,'y'=>60,'width'=>9];
        return ['fields'=>$fields];
    }

    public static function resolve(?array $layout, ?array $typography = null): array
    {
        $default = self::defaults($typography);
        if (!$layout) return $default;
        foreach ($default['fields'] as $key=>$field) {
            $value = $layout['fields'][$key] ?? [];
            if ($key === 'qr') {
                $width = max(8, min(20, (float) ($value['width'] ?? $field['width'])));
                $default['fields'][$key] = [
                    'x'=>max(0, min(100-$width, (float) ($value['x'] ?? $field['x']))),
                    'y'=>max(0, min(100-($width*297/100+3)*100/210, (float) ($value['y'] ?? $field['y']))),
                    'width'=>$width,
                ];
                continue;
            }
            $width = max($key==='photo' ? 2 : 5,min(100,(float)($value['width']??$field['width'])));
            $field['width'] = $width;
            $field['x'] = max(0,min(100-$width,(float)($value['x']??$field['x'])));
            $field['y'] = max(0,min(95,(float)($value['y']??$field['y'])));
            if ($key==='photo') {
                $field['height'] = max(2,min(40,(float)($value['height']??$field['height'])));
                $field['y'] = min(100-$field['height'],$field['y']);
            } else {
                $style = K3CertificateTypography::resolve([$key=>$value])[$key];
                $field = array_replace($field,$style);
                $field['text'] = mb_substr((string)($value['text']??$field['text']),0,1000);
                $field['align'] = in_array($value['align']??'', ['left','center','right'],true) ? $value['align'] : 'center';
                $field['bold'] = (bool)($value['bold']??$field['bold']);
                $field['bold_first_line'] = (bool)($value['bold_first_line']??$field['bold_first_line']);
            }
            $default['fields'][$key] = $field;
        }
        return $default;
    }

    public static function tokens(object $certificate, $settings): array
    {
        $date = $settings?->given_date;
        return [
            'student_name'=>mb_strtoupper(trim($certificate->full_name_en??'')),
            'class'=>ltrim($certificate->class_name,'-'), 'campus'=>$certificate->campus_name_en,
            'certificate_number'=>$certificate->certificate_number ?: 'Not assigned',
            'given_day'=>$date?->format('jS')??'[day]', 'given_month'=>$date?->format('F')??'[month]',
            'given_year'=>$date?->format('Y')??'[year]', 'given_date'=>$date?->format('j F Y')??'[Given Date]',
        ];
    }

    public static function text(string $text, array $values): string
    {
        return strtr($text, array_combine(array_map(fn($key)=>'{{'.$key.'}}',array_keys($values)),array_values($values)));
    }

    public static function rules(): array
    {
        $keys = array_keys(self::defaults()['fields']);
        $rules = ['fields'=>['required','array:'.implode(',',$keys)]];
        foreach ($keys as $key) {
            if ($key === 'qr') {
                // Existing saved K3 templates do not yet include a QR position.
                $rules['fields.qr'] = ['sometimes','array:x,y,width'];
                $rules['fields.qr.x'] = ['required_with:fields.qr','numeric','between:0,98'];
                $rules['fields.qr.y'] = ['required_with:fields.qr','numeric','between:0,95'];
                $rules['fields.qr.width'] = ['required_with:fields.qr','numeric','between:8,20'];
                continue;
            }
            $allowed = $key==='photo' ? 'x,y,width,height' : 'text,x,y,width,font,size,color,align,bold,bold_first_line';
            $rules["fields.$key"] = ['required','array:'.$allowed];
            $rules["fields.$key.x"] = ['required','numeric','between:0,98'];
            $rules["fields.$key.y"] = ['required','numeric','between:0,95'];
            $rules["fields.$key.width"] = ['required','numeric','between:'.($key==='photo'?2:5).',100'];
            if ($key==='photo') {
                $rules["fields.$key.height"] = ['required','numeric','between:2,40'];
                continue;
            }
            $rules["fields.$key.text"] = ['present','nullable','string','max:1000',function($attribute,$value,$fail) {
                preg_match_all('/\{\{([^{}]+)\}\}/',$value??'',$matches);
                foreach ($matches[1] as $token) if (!in_array($token,['student_name','class','campus','certificate_number','given_day','given_month','given_year','given_date'],true)) $fail('Unknown certificate placeholder: '.$token);
            }];
            $rules["fields.$key.font"] = ['required',Rule::in(array_keys(K3CertificateTypography::fonts()))];
            $rules["fields.$key.size"] = ['required','numeric','between:6,60'];
            $rules["fields.$key.color"] = ['required','regex:/^#[0-9a-fA-F]{6}$/'];
            $rules["fields.$key.align"] = ['required','in:left,center,right'];
            $rules["fields.$key.bold"] = ['required','boolean'];
            $rules["fields.$key.bold_first_line"] = ['required','boolean'];
        }
        return $rules;
    }
}
