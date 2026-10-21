<?php
namespace App\Support;

use Illuminate\Validation\{Rule,ValidationException};

class ScholarshipCertificateLayout
{
    public const TYPES=['class'=>'Class Rank','exam'=>'Exam Place','subject'=>'Best in Subject'];
    public const TOKENS=['student_name','campus','certificate_number','place','grade','class','academic_year','scholarship_year','award_percent','subject','issue_date','location','signer_1_name','signer_1_position','signer_2_name','signer_2_position'];
    public static function fonts(): array
    {
        return [
            'schcertsans'=>['label'=>'DejaVu Sans','file'=>'DejaVuSans.ttf','bold'=>'DejaVuSans-Bold.ttf','directory'=>'certificates'],
            'schcertserif'=>['label'=>'DejaVu Serif','file'=>'DejaVuSerif.ttf','bold'=>'DejaVuSerif-Bold.ttf','directory'=>'certificates'],
            'schcertmaiandra'=>['label'=>'Maiandra GD','file'=>'MaiandraGD.ttf','directory'=>'certificates'],
            'schcertold'=>['label'=>'Old English Text MT','file'=>'OldEnglishTextMT.ttf','directory'=>'certificates'],
            'schcertsiemreap'=>['label'=>'Khmer OS Siemreap','file'=>'KhmerOSsiemreap.ttf','directory'=>'khmer'],
            'schcertbattambang'=>['label'=>'Khmer OS Battambang','file'=>'KhmerOSbattambang.ttf','directory'=>'khmer'],
            'schcertmuol'=>['label'=>'Khmer OS Muol Light','file'=>'KhmerOSmuollight.ttf','directory'=>'khmer'],
        ];
    }
    public static function defaults(string $type): array
    {
        $body=match($type){
            'class'=>"for demonstrating academic excellence\nby achieving {{place}} in Grade {{class}}\nin the Academic Year {{academic_year}}.",
            'exam'=>"for demonstrating academic excellence\nby achieving {{place}} in Grade {{grade}}\nduring the Academic Year {{academic_year}}, and earning a\n{{award_percent}}% scholarship for the Academic Year {{scholarship_year}}.",
            default=>"for achieving the distinction of Best in {{subject}} in Grade {{grade}}\nfor the Academic Year {{academic_year}}.\nIn recognition of this academic excellence,\na {{award_percent}}% scholarship is granted for {{scholarship_year}}.",
        };
        $definitions=[
            'campus'=>['Campus','{{campus}}',170,64,18,8,9,'#000000','schcertsans','right',false],
            'number'=>['Certificate Number','Cert. No.: {{certificate_number}}',42,76,126,8,11,'#000000','schcertserif','left',false],
            'title'=>['Title','Excellence Award',27,96,156,20,34,'#ed1c24','schcertmaiandra','center',false],
            'presented'=>['Presentation Line','This award is presented to',30,117,150,10,16,'#bd9200','schcertserif','center',false],
            'name'=>['Student Name','{{student_name}}',24,131,162,14,23,'#20206f','schcertserif','center',true],
            'body'=>['Award Content',$body,24,150,162,39,15,'#000000','schcertsans','center',false],
            'date'=>['Issue Date','Given in {{location}} on {{issue_date}}.',24,190,162,13,14,'#000000','schcertsans','center',false],
            'signer_1'=>['Left Signer',"{{signer_1_name}}\n{{signer_1_position}}",29,214,72,20,12,'#000000','schcertserif','center',true],
            'signer_2'=>['Right Signer',"{{signer_2_name}}\n{{signer_2_position}}",109,214,72,20,12,'#000000','schcertserif','center',true],
        ];
        $fields=[];
        foreach($definitions as $key=>[$label,$text,$x,$y,$width,$height,$size,$color,$font,$align,$bold])$fields[$key]=compact('label','text','x','y','width','height','size','color','font','align','bold')+['visible'=>true];
        $fields['logo']=['label'=>'Replacement Logo','text'=>'','x'=>28,'y'=>27,'width'=>70,'height'=>43,'size'=>12,'color'=>'#000000','font'=>'schcertsans','align'=>'center','bold'=>false,'visible'=>false];
        return ['fields'=>$fields,'logo_source'=>'branding1','offset_x'=>0,'offset_y'=>0];
    }
    public static function resolve(string $type,?array $saved): array
    {return array_replace_recursive(self::defaults($type),$saved??[]);}
    public static function validate(string $type,array $layout): array
    {
        $keys=array_keys(self::defaults($type)['fields']);
        $rules=['fields'=>['required','array:'.implode(',',$keys)],'logo_source'=>['required',Rule::in(['branding1','branding2','upload'])],
            'offset_x'=>['required','numeric','between:-10,10'],'offset_y'=>['required','numeric','between:-10,10']];
        foreach($keys as $key){
            $prefix='fields.'.$key;
            $rules[$prefix]=['required','array:label,text,x,y,width,height,size,color,font,align,bold,visible'];
            foreach(['label'=>120,'text'=>2000] as $field=>$max)$rules[$prefix.'.'.$field]=['present','nullable','string','max:'.$max];
            foreach(['x'=>[0,209],'y'=>[0,296],'width'=>[1,210],'height'=>[1,297],'size'=>[6,60]] as $field=>[$min,$max])$rules[$prefix.'.'.$field]=['required','numeric',"between:$min,$max"];
            $rules[$prefix.'.font']=['required',Rule::in(array_keys(self::fonts()))];$rules[$prefix.'.color']=['required','regex:/^#[0-9a-fA-F]{6}$/'];
            $rules[$prefix.'.align']=['required',Rule::in(['left','center','right'])];$rules[$prefix.'.bold']=['required','boolean'];$rules[$prefix.'.visible']=['required','boolean'];
        }
        $value=validator($layout,$rules)->validate();
        foreach($value['fields'] as $key=>$field){
            if($field['x']+$field['width']>210.01 || $field['y']+$field['height']>297.01
                || ($field['visible']&&($field['x']+$value['offset_x']<0 || $field['y']+$value['offset_y']<0 || $field['x']+$value['offset_x']+$field['width']>210.01 || $field['y']+$value['offset_y']+$field['height']>297.01)))throw ValidationException::withMessages(['layout'=>"$key must fit inside the A4 page, including print offsets."]);
            preg_match_all('/\{\{\s*([^}]+)\s*\}\}/',$field['text']??'',$matches);
            foreach($matches[1] as $token)if(!in_array(trim($token),self::TOKENS,true))throw ValidationException::withMessages(['layout'=>'Unknown automatic value: '.trim($token)]);
        }
        return $value;
    }
    public static function text(string $template,array $values): string
    {return preg_replace_callback('/\{\{\s*([^}]+)\s*\}\}/',fn($match)=>(string)($values[trim($match[1])]??''),$template);}
}
