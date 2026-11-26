<?php
namespace App\Services;

use App\Models\{GepStudent,GepEnrollment,User};
use App\Support\{GepStudentProfile as Profile,GepEnrollmentPermissions as Access,GepTransferNames as Names};
use Illuminate\Support\Facades\{DB,Validator};
use Illuminate\Validation\ValidationException;

class GepDataTransfer
{
    public const ENROLLMENT=['student_identifier','academic_year','term','campus','level','class','class_type','group','enrollment_status','enrolled_on','notes'];
    public const FAMILY=['student_identifier','academic_year','term','relationship_type','full_name_en','full_name_kh','phone','occupation','nationality','workplace'];
    public function columns(string $type): array
    {
        return match($type){'students'=>['student_identifier',...array_filter(Profile::FIELDS,fn($field)=>!str_ends_with($field,'_id')),...Names::fields()], 'enrollments'=>self::ENROLLMENT,'families'=>self::FAMILY,default=>[]};
    }
    public function samples(string $type): array
    {
        return array_map(function($i)use($type){
            $common=['student_identifier'=>'2619000'.$i];
            return match($type){
                'students'=>$common+['full_name_en'=>$i===1?'SAMPLE SOK DARA':'SAMPLE CHAN SREY','gender'=>$i===1?'M':'F','date_of_birth'=>'2010-01-0'.$i,'family_number'=>'GEP-F'.$common['student_identifier'],'home_phone'=>'+8551234567'.$i,'nationality_en'=>'Cambodian','birth_country_en'=>'Cambodia','address_country_en'=>'Cambodia','birth_province_en'=>'Phnom Penh','address_province_en'=>'Phnom Penh','address_district_en'=>'Sen Sok','address_commune_en'=>'Tuek Thla','address_village_en'=>'Sample village '.$i,'address_house_no_en'=>'12'.$i,'address_street_en'=>'10'.$i,'current_address_en'=>'Sample address '.$i,'email'=>'sample'.$i.'@example.com'],
                'enrollments'=>$common+['academic_year'=>'2026','term'=>'Term 1','campus'=>'STD','level'=>'Level 1','class'=>'A','class_type'=>'Full-Time','group'=>'M','enrollment_status'=>'active','enrolled_on'=>'2026-11-26','notes'=>'Sample only: replace settings with exact names from GEP Settings.'],
                'families'=>$common+['academic_year'=>'2026','term'=>'Term 1','relationship_type'=>$i===1?'mother':'father','full_name_en'=>'SAMPLE PARENT '.$i,'phone'=>'+8551234567'.$i,'nationality'=>'Cambodia','occupation'=>'Teacher','workplace'=>'Sample workplace'],
            };
        },[1,2]);
    }
    private function lookup(string $table,string $column,string $value,array $parent=[]): int
    {
        $query=DB::table($table)->where($column,$value);foreach($parent as $key=>$id)$query->where($key,$id);
        $ids=$query->pluck('id');
        if($ids->count()!==1)throw new \RuntimeException("{$column}: '{$value}' must match exactly one setting within its parent.");
        return (int)$ids[0];
    }
    private function term(array $row): int
    {
        $year=$this->lookup('tb_gep_academic_year','academic_year',$row['academic_year']);
        $term=$this->lookup('tb_gep_term','term_name',$row['term']);
        return $this->lookup('tb_gep_term_assignment','term_id',(string)$term,['academic_year_id'=>$year]);
    }
    private function member(string $id): GepStudent { return GepStudent::where('student_identifier',$id)->firstOrFail(); }
    private function authorizeMember(User $user,GepStudent $member): void
    {
        foreach($member->enrollments()->withTrashed()->pluck('campus_id')->unique() as $campus)
            abort_unless(Access::allows($user,'edit',(int)$campus),403,'You cannot update this GEP profile outside your permitted campuses.');
        abort_unless(Access::allows($user,'create'),403);
    }
    public function import(string $type,array $rows,User $user): array
    {
        $counts=['created'=>0,'updated'=>0,'rows'=>count($rows),'unresolved_names'=>0];$keys=[];
        foreach($rows as $index=>$row){
            try{
                $required=match($type){'students'=>['student_identifier','full_name_en'],'enrollments'=>array_diff(self::ENROLLMENT,['notes']),'families'=>['student_identifier','academic_year','term','relationship_type','full_name_en']};
                foreach($required as $field)if(blank($row[$field]??null))throw new \RuntimeException("{$field} is required.");
                $key=implode('|',array_map(fn($f)=>$row[$f]??'',match($type){'students'=>['student_identifier'],'enrollments'=>['student_identifier','academic_year','term'],'families'=>['student_identifier','academic_year','term','relationship_type']}));
                if(isset($keys[$key]))throw new \RuntimeException('Duplicate record in this CSV.');$keys[$key]=true;
                if($type==='students'){
                    if(!preg_match('/^[0-9]{8}$/D',$row['student_identifier']))throw new \RuntimeException('Student ID must contain eight digits: YY + six-digit sequence.');
                    $member=GepStudent::where('student_identifier',$row['student_identifier'])->first();$new=!$member;
                    if($member)$this->authorizeMember($user,$member);else abort_unless(Access::allows($user,'create'),403);
                    $profile=array_filter(array_intersect_key($row,array_flip(array_filter(Profile::FIELDS,fn($field)=>!str_ends_with($field,'_id')))),fn($value)=>filled($value));
                    Validator::make($row,array_fill_keys(Names::fields(),['nullable','string','max:2000']))->validate();
                    $unresolved=[];
                    $profile=Names::import($row,array_replace($member?->profile??[],$profile),$unresolved);
                    $counts['unresolved_names']+=count($unresolved);
                    $rules=Profile::rules();$rules['full_name_en']=['required','string','max:160'];Validator::make($profile,$rules)->validate();
                    $profile['home_phone']=isset($profile['home_phone'])?Profile::phone($profile['home_phone']):($member?->profile['home_phone']??null);
                    if($new)$member=new GepStudent(['source'=>'new','student_identifier'=>$row['student_identifier']]);
                    // Blank CSV cells never clear saved information; WIS records are untouched.
                    $member->profile=array_replace($member->profile??[],$profile);$member->save();
                }else{
                    $member=$this->member($row['student_identifier']);$assignment=$this->term($row);
                    if($type==='enrollments'){
                        $campus=$this->lookup('tb_school_info','campus_name_en',$row['campus']);
                        $enrollment=GepEnrollment::where('gep_student_id',$member->id)->where('term_assignment_id',$assignment)->first();$new=!$enrollment;
                        abort_unless(Access::allows($user,$new?'create':'edit',$campus),403);
                        if($enrollment)abort_unless(Access::allows($user,'edit',$enrollment->campus_id),403);
                        if(!$enrollment)$enrollment=new GepEnrollment(['gep_student_id'=>$member->id,'term_assignment_id'=>$assignment,'created_by'=>$user->id]);
                        $before=$new?null:$enrollment->toArray();
                        Validator::make($row,['enrollment_status'=>'required|in:active,pending,completed,withdrawn,graduated','enrolled_on'=>'required|date_format:Y-m-d','notes'=>'nullable|string|max:2000'])->validate();
                        $values=['campus_id'=>$campus,'enrollment_status'=>$row['enrollment_status'],'enrolled_on'=>$row['enrolled_on']];
                        foreach(['level'=>['level','level_name'],'class'=>['class','class_name'],'class_type'=>['class_type','type_name'],'group'=>['group','group_code']] as $name=>[$table,$column])$values[$name.'_id']=$this->lookup('tb_gep_'.$table,$column,$row[$name]);
                        if(filled($row['notes']??null))$values['notes']=$row['notes'];
                        $enrollment->fill($values);$enrollment->updated_by=$user->id;$enrollment->save();
                    }else{
                        $enrollment=GepEnrollment::where('gep_student_id',$member->id)->where('term_assignment_id',$assignment)->firstOrFail();
                        abort_unless(Access::allows($user,'edit',$enrollment->campus_id),403);
                        Validator::make($row,['relationship_type'=>'required|in:mother,father,guardian','full_name_en'=>'required|string|max:150','phone'=>'nullable|string|max:50','occupation'=>'nullable|string|max:150','nationality'=>'nullable|string|max:150','workplace'=>'nullable|string|max:150'])->validate();
                        $before=$enrollment->toArray();$parents=$enrollment->parent_information??[];$relationship=$row['relationship_type'];$new=empty($parents[$relationship]['name_en']);
                        $values=[];foreach(['full_name_en'=>'name_en','full_name_kh'=>'name_kh','phone'=>'phone','occupation'=>'occupation','nationality'=>'nationality','workplace'=>'workplace'] as $column=>$field)if(filled($row[$column]??null))$values[$field]=$field==='phone'?Profile::phone($row[$column]):$row[$column];
                        if(filled($row['nationality']??null)){
                            $values['nationality_country_id']=Names::country(['any'=>$row['nationality']]);
                            if(!$values['nationality_country_id'])$counts['unresolved_names']++;
                        }
                        if(filled($row['occupation']??null)){
                            $values['occupation_id']=Names::match('tb_occupation',['any'=>$row['occupation']],['any'=>['occupation_name_en','occupation_name_kh']]);
                            if(!$values['occupation_id'])$counts['unresolved_names']++;
                        }
                        $parents[$relationship]=array_replace($parents[$relationship]??[],$values);$enrollment->parent_information=$parents;
                        if($relationship==='guardian'){$enrollment->guardian_name=$parents[$relationship]['name_en'];$enrollment->guardian_phone=$parents[$relationship]['phone']??null;}
                        $enrollment->updated_by=$user->id;$enrollment->save();
                    }
                    DB::table('tb_gep_enrollment_history')->insert(['enrollment_id'=>$enrollment->id,'action'=>'imported','before_values'=>$before?json_encode($before,JSON_THROW_ON_ERROR):null,'after_values'=>json_encode($enrollment->toArray(),JSON_THROW_ON_ERROR),'changed_by'=>$user->id,'created_at'=>now(),'updated_at'=>now()]);
                }
                $counts[$new?'created':'updated']++;
            }catch(\Symfony\Component\HttpKernel\Exception\HttpException $error){throw $error;}
            catch(\Throwable $error){throw ValidationException::withMessages(['file'=>'CSV row '.($index+2).': '.($error instanceof ValidationException?implode(' ',array_merge(...array_values($error->errors()))):$error->getMessage())]);}
        }
        app(GepStudentIdAllocator::class)->synchronize();return $counts;
    }
    public function export(string $type,User $user): array
    {
        $campuses=Access::campuses($user)->pluck('id');
        $enrollments=GepEnrollment::with(['gepStudent','termAssignment.academicYear','termAssignment.term','campus','level','schoolClass','classType','group'])->whereIn('campus_id',$campuses)->orderBy('id')->get();
        if($type==='students'){
            $members=GepStudent::whereIn('id',$enrollments->pluck('gep_student_id'));
            if($user->isSuperAdmin()||$user->is_global)$members->orWhereDoesntHave('enrollments');
            return $members->orderBy('id')->get()->map(fn($s)=>['student_identifier'=>$s->student_identifier]+Names::export($s->profile??[]))->all();
        }
        $rows=[];foreach($enrollments as $record){
            $row=['student_identifier'=>$record->gepStudent->student_identifier,'academic_year'=>$record->termAssignment->academicYear->academic_year,'term'=>$record->termAssignment->term->term_name];
            if($type==='enrollments')$rows[]=$row+['campus'=>$record->campus->campus_name_en,'level'=>$record->level->level_name,'class'=>$record->schoolClass->class_name,'class_type'=>$record->classType->type_name,'group'=>$record->group->group_code,'enrollment_status'=>$record->enrollment_status,'enrolled_on'=>$record->enrolled_on?->format('Y-m-d'),'notes'=>$record->notes];
            else foreach($record->parent_information ?: (filled($record->guardian_name)?['guardian'=>['name_en'=>$record->guardian_name,'phone'=>$record->guardian_phone]]:[]) as $relationship=>$parent)if(filled($parent['name_en']??null)){
                $occupation=$parent['occupation_id']??null;$nationality=$parent['nationality_country_id']??null;
                $rows[]=$row+['relationship_type'=>$relationship,'full_name_en'=>$parent['name_en'],'full_name_kh'=>$parent['name_kh']??'','phone'=>$parent['phone']??'','occupation'=>filled($parent['occupation']??null)?$parent['occupation']:($occupation?DB::table('tb_occupation')->where('id',$occupation)->value('occupation_name_en'):''),'nationality'=>filled($parent['nationality']??null)?$parent['nationality']:($nationality?DB::table('tb_country')->where('id',$nationality)->value('country_name_en'):''),'workplace'=>$parent['workplace']??''];
            }
        }return $rows;
    }
}
