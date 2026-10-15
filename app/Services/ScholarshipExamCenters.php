<?php
namespace App\Services;

use App\Models\{ScholarshipExam,ScholarshipStudentRank,SchoolInfo,StudentEnrollment};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{DB,Validator};
use Illuminate\Validation\ValidationException;

class ScholarshipExamCenters
{
    public static function rules(): array
    {
        return [
            'exam_centers'=>['sometimes','array','min:1','max:50'],
            'exam_centers.*'=>['array:id,key,name'],'exam_centers.*.id'=>['nullable','integer','distinct','min:1'],
            'exam_centers.*.key'=>['required','string','max:64','distinct'],
            'exam_centers.*.name'=>['required','string','max:160'],
            'center_assignments'=>['nullable','array'],'center_assignments.*'=>['required','string','max:64'],
        ];
    }

    public function campuses(int $year): Collection
    {
        $counts=StudentEnrollment::where('academic_year_id',$year)->whereIn('id',ScholarshipStudentRank::select('student_enrollment_id'))
            ->selectRaw('campus_id, COUNT(*) as total')->groupBy('campus_id')->pluck('total','campus_id');
        return SchoolInfo::whereIn('id',$counts->keys())->orderBy('campus_name_en')->get()->map(fn($campus)=>[
            'id'=>$campus->id,'label'=>$campus->campus_name_en?:$campus->campus_name_kh,'count'=>(int)$counts[$campus->id],
        ]);
    }

    /** Called inside the exam/year transaction after permission and lock checks. */
    public function save(ScholarshipExam $exam,array $data,array $campusIds): void
    {
        if(!array_key_exists('exam_centers',$data))return; // Preserve historical/legacy configurations.
        $data=Validator::make($data,self::rules())->validate();
        $centers=$data['exam_centers'];$assignments=$data['center_assignments']??[];$keys=array_column($centers,'key');
        $names=[];$existing=$exam->centers()->pluck('id')->all();
        foreach($centers as $index=>$center){
            $name=mb_strtolower(trim($center['name']));
            if($name===''||in_array($name,$names,true))throw ValidationException::withMessages(['exam_centers.'.$index.'.name'=>'Enter a different name for each exam center.']);
            $names[]=$name;
            if(!empty($center['id'])&&!in_array((int)$center['id'],$existing,true))throw ValidationException::withMessages(['exam_centers.'.$index.'.id'=>'Choose an exam center belonging to this academic year.']);
        }
        foreach($assignments as $campus=>$key){
            if(!ctype_digit((string)$campus)||(string)(int)$campus!==(string)$campus||!in_array((int)$campus,$campusIds,true))throw ValidationException::withMessages(['center_assignments'=>'Only campuses with assigned student ranks in this academic year can be assigned to a center.']);
            if(!in_array($key,$keys,true))throw ValidationException::withMessages(['center_assignments.'.$campus=>'Select an exam center from the list above.']);
        }
        foreach($campusIds as $campus)if(empty($assignments[$campus]))throw ValidationException::withMessages(['center_assignments.'.$campus=>'Select an exam center for every participating campus.']);
        $exam->candidates()->update(['exam_center_id'=>null,'exam_center_name'=>null,'seat_scope'=>0]);
        DB::table('tb_scholarship_center_campus')->where('exam_id',$exam->id)->delete();
        $ids=[];
        foreach(array_values($centers) as $position=>$center){
            $record=!empty($center['id'])?$exam->centers()->findOrFail($center['id']):$exam->centers()->make();
            $record->fill(['name'=>trim($center['name']),'position'=>$position])->save();$ids[$center['key']]=$record->id;
        }
        foreach($assignments as $campus=>$key)DB::table('tb_scholarship_center_campus')->insert(['exam_id'=>$exam->id,'exam_center_id'=>$ids[$key],'campus_id'=>(int)$campus]);
        $exam->centers()->whereNotIn('id',array_values($ids))->delete();$exam->unsetRelation('centers');
    }
}
