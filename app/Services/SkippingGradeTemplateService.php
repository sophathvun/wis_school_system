<?php

namespace App\Services;

use App\Http\Controllers\StudentSkippingGradeController;
use App\Models\SkippingGradeSetting;
use App\Models\StudentSkippingGrade;
use Illuminate\Support\HtmlString;

class SkippingGradeTemplateService
{
    private array $definitionCache = [];
    public const FONTS = ['Default', 'Khmer OS Siemreap', 'Khmer OS Muol Light', 'Arial', 'Times New Roman', 'Georgia', 'Old English Text MT', 'Maiandra GD'];

    public function defaults(string $form): array
    {
        if ($form === 'request') {
            $blocks = [
                'heading-kh'=>'ពាក្យសុំឡើងថ្នាក់', 'heading-en'=>'Grade Skipping Application Form',
                'label-name-kh'=>'ឈ្មោះសិស្ស៖', 'label-name-en'=>'Student’s Name', 'student-name'=>'{student_name_en}',
                'label-id-kh'=>'អត្តលេខសិស្ស៖', 'label-id-en'=>'Student ID', 'student-id'=>'{student_id}',
                'label-current-kh'=>'ថ្នាក់បច្ចុប្បន្ន៖', 'label-current-en'=>'Current Grade', 'current-grade'=>'{current_grade}',
                'label-target-kh'=>'ស្នើសុំឡើងថ្នាក់៖', 'label-target-en'=>'Requested Grade', 'target-grade'=>'{requested_grade}',
                'label-year-kh'=>'ឆ្នាំសិក្សា៖', 'label-year-en'=>'Academic Year', 'year'=>'{requested_academic_year}',
                'label-dob-kh'=>'ថ្ងៃខែឆ្នាំកំណើត៖', 'label-dob-en'=>'Date of Birth', 'dob'=>'{date_of_birth}',
                'label-campus-kh'=>'សាខា៖', 'label-campus-en'=>'Campus', 'campus'=>'{campus_en}',
                'label-parent-kh'=>'មាតាបិតា/អាណាព្យាបាល៖', 'label-parent-en'=>'Parent/Guardian Name', 'parent'=>'{parent_name}',
                'label-signature-kh'=>'ហត្ថលេខា៖', 'label-signature-en'=>'Signature',
                'label-phone-kh'=>'លេខទូរស័ព្ទ៖', 'label-phone-en'=>'Telephone', 'phone'=>'{parent_phone}',
                'label-date-kh'=>'ថ្ងៃធ្វើពាក្យ៖', 'label-date-en'=>'Date Completed', 'date'=>'{application_date}',
                'criteria-heading-kh'=>'លក្ខខណ្ឌសម្រាប់សិស្សសុំឡើងថ្នាក់', 'criteria-heading-en'=>'Criteria for Students Applying for Grade Skipping',
                'average-label'=>'Overall Average:', 'average'=>'{average_score} / {average_scale}',
                'reason-label'=>'Additional Information:', 'reason'=>'{reason}',
                'committee-heading-kh'=>'ការឯកភាពរបស់គណៈកម្មការ', 'committee-heading-en'=>'Committee Approval',
                'column-position'=>'Position', 'column-name'=>'Name', 'column-signature'=>'Signature', 'column-date'=>'Date',
                'footer-version'=>'Version 6', 'footer-reference'=>'Request #{request_id}',
            ];
            foreach (StudentSkippingGradeController::CRITERIA as $key=>$criterion) {
                $blocks['criterion-'.$key.'-kh']=$criterion['kh'];
                $blocks['criterion-'.$key.'-en']=$criterion['en'];
            }
            foreach (SkippingGradeSetting::COMMITTEE as $index=>$role) {
                $blocks['committee-role-'.$index]=($index+1).'. '.$role;
                $blocks['committee-name-'.$index]='{committee_name_'.$index.'}';
            }
            return $blocks;
        }
        $blocks = [
            'motto-country'=>'ព្រះរាជាណាចក្រកម្ពុជា', 'motto-national'=>'ជាតិ សាសនា ព្រះមហាក្សត្រ',
            'motto-country-en'=>'KINGDOM OF CAMBODIA', 'motto-national-en'=>'NATION RELIGION KING',
            'reference'=>'លេខ/Nº : {reference_number}', 'heading'=>'សេចក្តីសម្រេច ស្តីពី ការវាយតម្លៃសិស្សសុំផ្លោះថ្នាក់',
            'heading-en'=>'Decision on the Evaluation of Grade-Skipping Request',
            'label-name'=>'របស់សិស្សឈ្មោះ៖ ', 'label-name-en'=>"Student's Name", 'student-name'=>'{student_name_en}',
            'label-id'=>'អត្តលេខ៖ ', 'label-id-en'=>'Student ID', 'student-id'=>'{student_id}',
            'label-target'=>'ស្នើសុំផ្លោះចូលថ្នាក់ទី៖ ', 'label-target-en'=>'Requested Grade', 'target-grade'=>'{requested_grade_number}', 'committee-heading'=>'គណៈកម្មការ / Committee',
            'references-heading-kh'=>'យោង / ', 'references-heading-en'=>'References៖',
            'reference-rules'=>'សេចក្តីសម្រេចស្តីពីការបង្កើតគណៈកម្មការវាយសម្លៃសិស្សផ្លោះថ្នាក់',
            'reference-rules-en'=>'Decision on the establishment of the Grade-Skipping Evaluation Committee',
            'reference-received'=>'តាមស្មារតីនៃអង្គប្រជុំរបស់គណៈកម្មការវាយតម្លៃសិស្សផ្លោះថ្នាក់នៅថ្ងៃទី {approval_date_reference_kh}។',
            'reference-received-en'=>'Resolution of the Grade-Skipping Evaluation Committee meeting on {approval_date_reference_en}.',
            'reference-reviewed'=>'តាមពាក្យស្នើសុំរបស់មាតាបីតាសិស្សនៅថ្ងៃទី {application_date_long_kh}',
            'reference-reviewed-en'=>'Grade-Skipping Application submitted by the parents on {application_date}', 'decision-heading'=>'គណៈកម្មការសម្រេច / The Committee Decides',
            'article-1-heading'=>'ប្រការ ១. ការផ្លោះថ្នាក់', 'article-1-heading-en'=>'Article 1. Grade Skipping',
            'age-standard'=>'អនុញ្ញាតឱ្យសិស្សដែលមានឈ្មោះដូចខាងលើផ្លោះចូលថ្នាក់តាមការស្នើសុំ។',
            'age-standard-en'=>'To allow the above-named student to skip the grade as requested',
            'age-exception'=>'មិនអនុញ្ញាតឱ្យសិស្សដែលមានឈ្មោះដូចខាងល់ើផ្លោះចូលថ្នាក់តាមការស្នើសុំទេ។',
            'age-exception-en'=>'Not to allow the above-named student to skip the grade as requested.',
            'article-2-heading'=>'ប្រការ ២. មូលហេតុនៃការសម្រេច', 'article-2-heading-en'=>'Reasons for the Decision',
            'school-internal'=>'សិស្សបានបំពេញគ្រប់លក្ខណៈវិនិច្ឆ័យ', 'school-internal-en'=>'The student has fulfilled all criteria',
            'school-external'=>'សិស្សមកពីសាលាផ្សេង ដោយមានឯកសារគាំទ្រគ្រប់គ្រាន់។',
            'average'=>'({average_score} / {average_scale})', 'article-3-heading'=>'ប្រការ ៣. កាតព្វកិច្ចសិស្ស', 'article-3-heading-en'=>"Student's Obligations",
            'obligation-conduct'=>'សិស្សត្រូវចូលរៀនវគ្គបំប៉នវិស្សមកាល', 'obligation-conduct-en'=>'The student must attend a summer remedial program',
            'obligation-rules'=>'សិស្សត្រូវបំប៉នភាសាអង់គ្លេសបន្ថែម', 'obligation-rules-en'=>'The student must find a tutor for weak subjects',
            'obligation-study'=>'សិស្សត្រូវរកគ្រូបង្រៀនបន្ថែមលើមុខវិជ្ជាដែលខ្សោយ', 'obligation-study-en'=>'The student must take additional English classes', 'article-4-heading'=>'ប្រការ ៤. ការអនុវត្ត', 'article-4-heading-en'=>'Article 4. Implementation',
            'implementation'=>'បុគ្គលិកទទួលព័ត៌មាន លោកគ្រូអ្នកគ្រូ ការិយាល័យសិក្សា ការិយាល័យរដ្ឋបាល ការិយាល័យគណនេយ្យ នាយកសាខាគ្រប់សាខាទាំងអស់ត្រូវគោរពតាមខ្លឹមសារនៃសេចក្តីសម្រេចនេះឱ្យមានប្រសិទ្ធិភាពចាប់ពីថ្ងៃចុះហត្ថលេខានេះតទៅ។',
            'implementation-en'=>"Receptionists, teachers, the Registrar's Office, the Administration Office, the Accounting Office, and School Principals of all campuses must strictly implement this decision from the date of signature onwards.",
            'notes'=>'{approval_notes}', 'signoff-date'=>'រាជធានីភ្នំពេញ {approval_date_long_kh}', 'signoff-date-en'=>'Phnom Penh, {approval_date_long_en}.', 'signoff-title'=>'ប្រធានគណៈកម្មការ', 'signoff-name'=>'{signer_name_kh}',
        ];
        foreach (StudentSkippingGradeController::CRITERIA as $key=>$criterion) $blocks['criterion-'.$key]=$criterion['kh'];
        return $blocks;
    }

    public function values(StudentSkippingGrade $record, array $committeeNames): array
    {
        $s=$record->student_snapshot;
        $gradeNumber=$s['target_grade'];
        if (preg_match('/[0-9០-៩]+/u',$gradeNumber,$matches)) $gradeNumber=strtr($matches[0],array_combine(['០','១','២','៣','៤','៥','៦','៧','៨','៩'],range(0,9)));
        $values = [
            'student_name_en'=>$s['name_en'], 'student_name_kh'=>$s['name_kh'], 'student_id'=>$s['student_id'],
            'current_grade'=>$s['source_grade'].$s['source_class'], 'requested_grade'=>$s['target_grade'].$s['target_class'],
            'requested_grade_number'=>$gradeNumber,
            'academic_year'=>$s['target_academic_year']??$s['academic_year'], 'campus_en'=>$s['campus_en'], 'campus_kh'=>$s['campus_kh']??'',
            'source_academic_year'=>$s['academic_year'],'requested_academic_year'=>$s['target_academic_year']??$s['academic_year'],
            'date_of_birth'=>!empty($s['date_of_birth'])?\Carbon\Carbon::parse($s['date_of_birth'])->format('d-M-Y'):'',
            'parent_name'=>$record->parent_name, 'parent_phone'=>$record->parent_phone??'', 'application_date'=>$record->application_date->format('d-M-Y'),
            'request_id'=>(string)$record->id, 'reference_number'=>$record->reference_number??'',
            'average_score'=>(string)($record->average_score??'____________'), 'average_scale'=>(string)$record->average_scale, 'reason'=>$record->reason??'',
            'approval_notes'=>$record->approval_snapshot['notes']??'', 'signer_title_kh'=>$record->approval_snapshot['signer_title_kh']??'',
            'signer_name_kh'=>$record->approval_snapshot['signer_name_kh']??'',
        ];
        foreach (['received_date','review_date','effective_date','approval_date'] as $key) {
            $values[$key.'_kh']=$record->$key?strtr($record->$key->format('d-m-Y'),array_combine(range(0,9),['០','១','២','៣','៤','៥','៦','៧','៨','៩'])):'';
        }
        $reviewDate=$record->review_date;
        $khmerMonths=['មករា','កុម្ភៈ','មីនា','មេសា','ឧសភា','មិថុនា','កក្កដា','សីហា','កញ្ញា','តុលា','វិច្ឆិកា','ធ្នូ'];
        foreach (['review_date','application_date'] as $key) {
            $date=$record->$key;
            $values[$key.'_long_kh']=$date?strtr($date->format('d').' '.$khmerMonths[$date->month-1].' '.$date->format('Y'),array_combine(range(0,9),['០','១','២','៣','៤','៥','៦','៧','៨','៩'])):'';
        }
        $values['review_date_long_en']=$reviewDate?$reviewDate->format('d-F-Y'):'';
        $approvalDate=$record->approval_date;
        $values['approval_date_reference_kh']=$approvalDate?strtr($approvalDate->format('d').' '.$khmerMonths[$approvalDate->month-1].' '.$approvalDate->format('Y'),array_combine(range(0,9),['០','១','២','៣','៤','៥','៦','៧','៨','៩'])):'';
        $values['approval_date_reference_en']=$approvalDate?$approvalDate->format('d-F-Y'):'';
        $values['approval_date_long_kh']=$approvalDate?strtr('ថ្ងៃទី'.$approvalDate->format('d').' ខែ'.$khmerMonths[$approvalDate->month-1].' ឆ្នាំ'.$approvalDate->format('Y'),array_combine(range(0,9),['០','១','២','៣','៤','៥','៦','៧','៨','៩'])):'';
        $values['approval_date_long_en']=$approvalDate?$approvalDate->format('F j, Y'):'';
        foreach (SkippingGradeSetting::COMMITTEE as $index=>$role) $values['committee_name_'.$index]=$committeeNames[$index]??'';
        return $values;
    }

    public function definitions(string $form): array
    {
        if (isset($this->definitionCache[$form])) return $this->definitionCache[$form];
        $definitions=[];
        foreach ($this->defaults($form) as $key=>$text) $definitions[$key]=['type'=>'text','text'=>$text];
        $definitions['school-logo']=['type'=>'image','width'=>$form==='request'?57:60,'height'=>$form==='request'?18:27];
        if ($form==='request') {
            $definitions['average'] += ['border_style'=>'solid','border_width'=>1,'border_color'=>'#333333'];
            $definitions['average']['type']='score';
            foreach (['student-name','student-id','current-grade','target-grade','year','dob','campus','parent','parent-signature','phone','date'] as $key) {
                $definitions['line-'.$key]=['type'=>'line','border_style'=>'dotted','border_width'=>1,'border_color'=>'#666666'];
            }
            foreach (SkippingGradeSetting::COMMITTEE as $index=>$role) foreach (['position','name','signature','date'] as $column) {
                $definitions['committee-line-'.$index.'-'.$column]=['type'=>'line','border_style'=>'dotted','border_width'=>1,'border_color'=>'#999999'];
            }
            foreach (array_keys(StudentSkippingGradeController::CRITERIA) as $key) $definitions['criteria-check-'.$key]=$this->checkboxDefinition();
            $definitions['committee-divider']=['type'=>'line','border_style'=>'solid','border_width'=>2,'border_color'=>'#233e69'];
        } else {
            $definitions['student-divider']=['type'=>'line','border_style'=>'dotted','border_width'=>1,'border_color'=>'#555555'];
            foreach (['age-standard','age-exception','school-internal','school-external','criterion-age','criterion-average','criterion-documents','criterion-recommendation','obligation-conduct','obligation-rules','obligation-study'] as $key) {
                $definitions['check-'.$key]=$this->checkboxDefinition();
            }
        }
        return $this->definitionCache[$form]=$definitions;
    }

    private function checkboxDefinition(): array
    {
        return ['type'=>'checkbox','width'=>4,'height'=>4,'border_style'=>'solid','border_width'=>1,'border_color'=>'#333333'];
    }

    public function isCustom(string $key): bool
    {
        return (bool)preg_match('/^custom-[a-z0-9-]{1,48}$/D',$key);
    }

    public function checkboxOptions(array $template): array
    {
        $options=[];
        foreach ($template as $key=>$block) {
            if (!$this->isCustom((string)$key) || ($block['type']??null)!=='checkbox' || !empty($block['removed']) || !empty($block['checkbox_source'])) continue;
            $options[$key]=['label'=>trim($block['option_label']??'') ?: 'Checkbox '.substr($key,-6),'default'=>!empty($block['checked'])];
        }
        return $options;
    }

    public function checkboxSources(string $form): array
    {
        $sources=[];
        if ($form==='approval') $sources=[
            'check-age-standard'=>'Decision: Approved',
            'check-age-exception'=>'Decision: Rejected',
            'check-school-internal'=>'School: Western International School',
            'check-school-external'=>'School: Another school',
            'check-obligation-conduct'=>'Obligation: Maintain good conduct',
            'check-obligation-rules'=>'Obligation: Follow school regulations',
            'check-obligation-study'=>'Obligation: Fulfil study obligations',
        ];
        foreach (StudentSkippingGradeController::CRITERIA as $key=>$criterion) {
            $sources[($form==='request'?'criteria-check-':'check-criterion-').$key]='Criterion: '.$criterion['en'];
        }
        return $sources;
    }

    public function checkboxValues(string $form, StudentSkippingGrade $record): array
    {
        $values=[];
        foreach (array_keys(StudentSkippingGradeController::CRITERIA) as $key) {
            $values[($form==='request'?'criteria-check-':'check-criterion-').$key]=(bool)($record->criteria[$key]??false);
        }
        if ($form==='approval') {
            $values['check-age-standard']=$record->status==='approved';
            $values['check-age-exception']=$record->status==='rejected';
            foreach (['internal','external'] as $key) $values['check-school-'.$key]=($record->approval_snapshot['school_decision']??'internal')===$key;
            foreach (['conduct','rules','study'] as $key) $values['check-obligation-'.$key]=(bool)($record->approval_snapshot['obligations'][$key]??false);
        }
        return $values;
    }

    public function captureCheckboxOptions(array $template, array $selected): array
    {
        $options=$this->checkboxOptions($template);
        foreach ($selected as $key=>$value) {
            if (!isset($options[$key]) || !in_array($value,[true,false,0,1,'0','1'],true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['custom_options.'.$key=>'This checkbox option changed. Refresh the form and try again.']);
            }
        }
        $saved=[];
        foreach ($options as $key=>$option) $saved[$key]=['label'=>$option['label'],'checked'=>(bool)($selected[$key]??false)];
        return $saved;
    }

    private function styles(array $block, string $type, bool $custom): string
    {
        $style='';
        if (isset($block['font']) && in_array($block['font'],self::FONTS,true) && $block['font']!=='Default') $style.='font-family:"'.$block['font'].'";';
        foreach (['size'=>'font-size','width'=>'width','height'=>'height'] as $field=>$property) {
            if (isset($block[$field]) && is_numeric($block[$field])) $style.=$property.':'.(float)$block[$field].($field==='size'?'pt':'mm').';';
        }
        $left=(float)($block['left']??0); $top=(float)($block['top']??0);
        if ($custom || in_array($type,['line','box','checkbox','image'],true)) $style.='transform:translate('.$left.'mm,'.$top.'mm);';
        else {
            if (isset($block['left'])) $style.='left:'.$left.'mm;';
            if (isset($block['top'])) $style.='top:'.$top.'mm;';
        }
        foreach (['color'=>'color','border_color'=>'border-color','fill'=>'background-color'] as $field=>$property) {
            if (isset($block[$field]) && (preg_match('/^#[0-9a-f]{6}$/i',$block[$field]) || ($field==='fill' && $block[$field]==='transparent'))) $style.=$property.':'.$block[$field].';';
        }
        if (isset($block['border_width'])) $style.=($type==='line'?'border-top-width':'border-width').':'.(float)$block['border_width'].'px;';
        if (isset($block['border_style']) && in_array($block['border_style'],['none','solid','dashed','dotted'],true)) $style.=($type==='line'?'border-top-style':'border-style').':'.$block['border_style'].';';
        if (isset($block['bold'])) $style.='font-weight:'.($block['bold']?'700':'400').';';
        if (in_array($type,['text','score'],true) && (isset($block['width']) || isset($block['height']))) $style.='display:inline-block;';
        return $style;
    }

    private function element(string $key, array $definition, array $block, array $values, bool $custom = false, bool $checked = false): HtmlString
    {
        $type=$definition['type'];
        $content=$block['text']??$definition['text']??'';
        if (!in_array($type,['text','score'],true)) $content='';
        $tokens=[];
        foreach ($values as $name=>$value) $tokens['{'.$name.'}']=$value;
        $classes='skipping-template-object skipping-template-'.$type;
        if ($type==='score') $classes.=' skipping-template-text';
        if ($custom) $classes.=' skipping-template-custom';
        if ($type==='checkbox') $classes.=' print-check'.($checked?' checked':'');
        if (in_array($key,['check-age-standard','check-age-exception','check-school-internal','check-obligation-conduct','check-obligation-rules','check-obligation-study'],true)) $classes.=' skipping-template-premium-check';
        $rendered=e(strtr($content,$tokens));
        if ($type==='text' && !empty($block['bullet'])) {
            $classes.=' skipping-template-bulleted';
            $rendered=implode('',array_map(fn($line)=>trim($line)===''
                ?'<span class="skipping-template-bullet-space" aria-hidden="true"> </span>'
                :'<span class="skipping-template-bullet-row"><span class="skipping-template-bullet-marker" aria-hidden="true">•</span><span>'.e($line).'</span></span>',preg_split('/\r\n|\r|\n/',strtr($content,$tokens))));
        }
        if (in_array($key,['committee-heading','decision-heading'],true)) {
            $rendered=preg_replace('/[\x{1780}-\x{17FF}\x{19E0}-\x{19FF}]+/u','<span class="approval-committee-kh">$0</span>',$rendered);
        }
        $removed=!empty($block['removed']);
        if ($removed) $classes.=' is-removed';
        $style=$this->styles($block,$type,$custom);
        return new HtmlString('<span class="'.e($classes).'" data-skipping-block="'.e($key).'" data-object-type="'.e($type).'" data-removed="'.($removed?'true':'false').'" data-template-text="'.e($content).'" style="'.e($style).'">'.$rendered.'</span>');
    }

    public function text(string $form, string $key, array $template, array $values): HtmlString
    {
        return $this->element($key,$this->definitions($form)[$key],$template[$key]??[],$values);
    }

    public function shape(string $form, string $key, array $template, bool $checked = false): HtmlString
    {
        return $this->element($key,$this->definitions($form)[$key],$template[$key]??[],[],false,$checked);
    }

    public function logo(string $form, array $template, ?string $source, bool $preview = false): HtmlString
    {
        $block=$template['school-logo']??[];
        $removed=!empty($block['removed']);
        $content=$source?'<img src="'.e($source).'" alt="Western International School">'
            :($preview?'<span class="skipping-template-image-placeholder">School logo</span>':($form==='request'?'<strong>WESTERN INTERNATIONAL SCHOOL</strong>':''));
        return new HtmlString('<span class="skipping-print-logo skipping-template-object skipping-template-image'.($removed?' is-removed':'').'" data-skipping-block="school-logo" data-object-type="image" data-removed="'.($removed?'true':'false').'" style="'.e($this->styles($block,'image',false)).'">'.$content.'</span>');
    }

    public function extras(array $template, array $values, ?array $options = null, array $checkboxValues = []): HtmlString
    {
        $html='';
        foreach ($template as $key=>$block) {
            if (!$this->isCustom($key) || !in_array($block['type']??null,['text','box','line','checkbox'],true)) continue;
            $checked=!empty($block['checkbox_source'])?!empty($checkboxValues[$block['checkbox_source']]):($options===null?!empty($block['checked']):!empty($options[$key]['checked']));
            $html.=$this->element($key,['type'=>$block['type'],'text'=>''],$block,$values,true,$checked);
        }
        return new HtmlString($html);
    }

    public function revision(array $template): string
    {
        return hash('sha256',json_encode($template,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    }
}
