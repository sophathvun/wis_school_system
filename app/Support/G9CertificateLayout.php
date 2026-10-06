<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class G9CertificateLayout
{
    public static function defaults(?array $typography = null): array
    {
        $styles = G9CertificateTypography::resolve($typography);
        $content = [
            'class_campus'=>['text'=>'9{{class}} / {{campus}}','x'=>77,'y'=>2.8,'width'=>20],
            'title'=>['text'=>'Junior High School Diploma','x'=>13,'y'=>30,'width'=>74,'curve'=>26],
            'heading'=>['text'=>'This certifies that','x'=>10,'y'=>38.1,'width'=>80],
            'student_name'=>['text'=>'{{student_name}}','x'=>14,'y'=>42.4,'width'=>72],
            'completion'=>['text'=>"has successfully completed the required course of study prescribed\nby Western International School and is therefore awarded this",'x'=>10,'y'=>47.7,'width'=>80],
            'diploma'=>['text'=>'Diploma','x'=>20,'y'=>56,'width'=>60],
            'given_date'=>['text'=>'Given in Phnom Penh on the {{given_day}} day of {{given_month}} {{given_year}}','x'=>10,'y'=>63,'width'=>80],
            'chairman'=>['text'=>"H.E. Laurent Te\nChairman",'x'=>16,'y'=>83.7,'width'=>27,'bold_first_line'=>true],
            'director'=>['text'=>"VUN Sophath\nRegistrar Director",'x'=>54,'y'=>83.7,'width'=>27,'bold_first_line'=>true],
            'number'=>['text'=>'Nº:{{certificate_number}}','x'=>40.5,'y'=>88,'width'=>17],
        ];
        $fields = [];
        foreach ($content as $key=>$field) $fields[$key] = $field + $styles[$key] + ['bold'=>false,'bold_first_line'=>false,'align'=>'center'];
        $fields['photo'] = ['x'=>45.3,'y'=>74.2,'width'=>7.5,'height'=>12.4];
        $fields['qr'] = ['x'=>33,'y'=>68,'width'=>10];
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
                $style = G9CertificateTypography::resolve([$key=>$value])[$key];
                $field = array_replace($field,$style);
                $field['text'] = mb_substr((string)($value['text']??$field['text']),0,1000);
                $field['align'] = in_array($value['align']??'', ['left','center','right'],true) ? $value['align'] : 'center';
                $field['bold'] = (bool)($value['bold']??$field['bold']);
                $field['bold_first_line'] = (bool)($value['bold_first_line']??$field['bold_first_line']);
                if ($key === 'title') $field['curve'] = is_numeric($value['curve'] ?? null) ? max(0, min(90, (float) $value['curve'])) : $field['curve'];
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
                // Older saved templates do not yet have a QR position.
                $rules['fields.qr'] = ['sometimes','array:x,y,width'];
                $rules['fields.qr.x'] = ['required_with:fields.qr','numeric','between:0,98'];
                $rules['fields.qr.y'] = ['required_with:fields.qr','numeric','between:0,95'];
                $rules['fields.qr.width'] = ['required_with:fields.qr','numeric','between:8,20'];
                continue;
            }
            $allowed = $key==='photo' ? 'x,y,width,height' : 'text,x,y,width,font,size,color,align,bold,bold_first_line';
            if ($key === 'title') $allowed .= ',curve';
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
            $rules["fields.$key.font"] = ['required',Rule::in(array_keys(G9CertificateTypography::fonts()))];
            $rules["fields.$key.size"] = ['required','numeric','between:6,36'];
            $rules["fields.$key.color"] = ['required','regex:/^#[0-9a-fA-F]{6}$/'];
            $rules["fields.$key.align"] = ['required','in:left,center,right'];
            $rules["fields.$key.bold"] = ['required','boolean'];
            $rules["fields.$key.bold_first_line"] = ['required','boolean'];
            if ($key === 'title') $rules["fields.$key.curve"] = ['sometimes','numeric','between:0,90'];
        }
        return $rules;
    }
}
