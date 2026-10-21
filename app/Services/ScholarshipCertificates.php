<?php
namespace App\Services;

use App\Models\{AcademicYear,ScholarshipExam,ScholarshipCandidate,ScholarshipStudentRank,ScholarshipCertificate,ScholarshipCertificateSetting,ScholarshipEvent,User};
use App\Support\{ScholarshipPermissions,ScholarshipCertificateLayout};
use Illuminate\Support\{Collection,Arr};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScholarshipCertificates
{
    public function authorize(User $user,string $action): void
    {abort_unless(ScholarshipPermissions::allows($user,$action),403);}
    public function exam(AcademicYear $year): ?ScholarshipExam
    {return ScholarshipExam::where('academic_year_id',$year->id)->whereNull('historical_imported_at')->first();}
    public function settings(AcademicYear $year): array
    {
        $row=ScholarshipCertificateSetting::where('academic_year_id',$year->id)->first();
        preg_match('/(\d{4})\D+(\d{4})/',$year->academic_year,$years);$end=(int)($years[2]??now()->year);
        return ['version'=>$row?->version??0,'issue_date'=>$row?->issue_date?->format('Y-m-d')??now()->format('Y-m-d'),
            'location'=>$row?->location??'Phnom Penh','scholarship_year'=>$row?->scholarship_year??($end.'-'.($end+1)),
            'signers'=>$row?->signers??[['name'=>'','position'=>'Vice President'],['name'=>'','position'=>'Registrar Director']],
            'layouts'=>collect(ScholarshipCertificateLayout::TYPES)->mapWithKeys(fn($label,$type)=>[$type=>ScholarshipCertificateLayout::resolve($type,$row?->layouts[$type]??null)])->all()];
    }
    public function update(User $user,AcademicYear $year,int $version,array $data,string $action): array
    {
        $this->authorize($user,$action);
        return DB::transaction(function()use($user,$year,$version,$data,$action){
            AcademicYear::whereKey($year->id)->lockForUpdate()->firstOrFail();
            $setting=ScholarshipCertificateSetting::where('academic_year_id',$year->id)->lockForUpdate()->first();
            if(($setting?->version??0)!==$version)abort(409,'These settings changed in another session. Reload before saving.');
            $setting??=new ScholarshipCertificateSetting(['academic_year_id'=>$year->id]);
            $setting->fill($data);$setting->version=$version+1;$setting->save();
            $this->event($user,$year,$action,['version'=>$setting->version]);
            return $this->settings($year);
        });
    }
    private function event(User $user,AcademicYear $year,string $action,array $details): void
    {if($exam=$this->exam($year))ScholarshipEvent::create(['exam_id'=>$exam->id,'user_id'=>$user->id,'action'=>$action,'details'=>$details]);}
    public function requirePublished(AcademicYear $year): ScholarshipExam
    {
        $exam=$this->exam($year);
        if(!$exam?->published_at)throw ValidationException::withMessages(['certificates'=>'Publish the scholarship results before assigning or printing certificates.']);
        return $exam;
    }
    /** Global, score-free eligibility. Always exclude exam winners before applying campus filters. */
    public function eligible(AcademicYear $year): Collection
    {
        $exam=$this->exam($year);if(!$exam?->published_at)return collect();
        $awards=$exam->awards()->whereNotNull('candidate_id')->get();
        $candidates=ScholarshipCandidate::whereIn('id',$awards->pluck('candidate_id'))->get(['id','student_id','campus_id','grade_number','snapshot']);
        $byId=$candidates->keyBy('id');$slots=[];$rows=collect();
        foreach($awards as $award){
            $candidate=$byId->get($award->candidate_id);if(!$candidate)continue;
            $grade=(int)$candidate->grade_number;
            $slots[$grade]??=app(ScholarshipWorkflow::class)->awardSlots($exam,$grade);
            $slot=$slots[$grade]->firstWhere('key',$award->slot);if(!$slot)continue;
            $subject=str_starts_with($award->slot,'subject_');
            $position=$subject?$slots[$grade]->whereNotNull('subject_id')->values()->search(fn($s)=>$s['key']===$award->slot):100+(3-(int)substr($award->slot,-1));
            $snapshot=$candidate->snapshot??[];$class=$snapshot['grade_class']??trim(explode(' / ',(string)($snapshot['class']??''))[0]);
            if($class!==''&&!preg_match('/^\d/',$class))$class=$grade.$class;
            $rows->push(['student_id'=>(int)$candidate->student_id,'campus_id'=>(int)$candidate->campus_id,'campus'=>(string)($snapshot['campus']??''),
                'student_name'=>mb_strtoupper(trim((string)($snapshot['name_en']??''))),'grade'=>$grade,'class'=>$class,
                'type'=>$subject?'subject':'exam','place'=>$subject?'':$this->place((int)substr($award->slot,-1)),
                'subject'=>$subject?substr($slot['label'],8):'','award_percent'=>rtrim(rtrim(number_format($award->award_percent,2,'.',''),'0'),'.'),
                'academic_year'=>$year->academic_year,'source'=>'award:'.$award->id,'order'=>(int)$position]);
        }
        $rows=$rows->sort(fn($a,$b)=>($a['grade']<=>$b['grade'])?:($a['order']<=>$b['order'])?:($a['student_id']<=>$b['student_id']))->values();
        $winnerIds=$candidates->pluck('student_id')->filter()->unique();
        $classRows=ScholarshipStudentRank::whereIn('rank',[1,2,3])->whereHas('enrollment',fn($q)=>$q->where('academic_year_id',$year->id)->whereNotIn('student_id',$winnerIds))
            ->with(['enrollment.student','enrollment.campus','enrollment.grade','enrollment.schoolClass'])->get()
            ->filter(fn($rank)=>$rank->enrollment->grade&&app(ScholarshipWorkflow::class)->gradeNumber($rank->enrollment->grade))
            ->map(function($rank)use($year){
                $e=$rank->enrollment;$grade=app(ScholarshipWorkflow::class)->gradeNumber($e->grade);
                $class=(string)$e->schoolClass?->class_name;if(!preg_match('/^\d/',$class))$class=$grade.$class;
                return ['student_id'=>(int)$e->student_id,'campus_id'=>(int)$e->campus_id,'campus'=>(string)$e->campus?->campus_name_en,
                    'student_name'=>mb_strtoupper(trim((string)$e->student?->full_name_en)),'grade'=>$grade,'class'=>$class,'type'=>'class','place'=>$this->place($rank->rank),
                    'subject'=>'','award_percent'=>'','academic_year'=>$year->academic_year,'source'=>'class:'.$rank->id,'order'=>3-$rank->rank];
            })->sort(fn($a,$b)=>strnatcasecmp($a['campus'],$b['campus'])?:($a['grade']<=>$b['grade'])?:($a['order']<=>$b['order'])?:strnatcasecmp($a['class'],$b['class'])?:strcmp($a['student_name'],$b['student_name'])?:($a['student_id']<=>$b['student_id']));
        $combined=$rows->concat($classRows)->values();
        if($combined->contains(fn($r)=>!$r['student_id']||!$r['campus_id']||!$r['student_name']||!$r['grade']))throw ValidationException::withMessages(['certificates'=>'An eligible student is missing a student link, campus, name or grade. Correct the source record first.']);
        if($combined->pluck('student_id')->duplicates()->isNotEmpty())throw ValidationException::withMessages(['certificates'=>'A student has multiple eligible rank records. Correct duplicate assignments before issuing certificates.']);
        return $combined->map(function($row){unset($row['order']);$row['fingerprint']=hash('sha256',json_encode($row));return $row;});
    }
    private function place(int $rank): string {return [1=>'1st Place',2=>'2nd Place',3=>'3rd Place'][$rank]??'';}
    public function scope(User $user,string $action='tab-certificates'): array
    {
        $this->authorize($user,$action);
        return ScholarshipPermissions::campuses($user)->get()->filter(fn($c)=>ScholarshipPermissions::allows($user,$action,$c->id))->modelKeys();
    }
    public function plan(AcademicYear $year): array
    {
        $exam=$this->requirePublished($year);$eligible=$this->eligible($year);
        $existing=ScholarshipCertificate::where('academic_year_id',$year->id)->orderBy('sequence')->get();$sequence=(int)$existing->max('sequence');
        preg_match('/(\d{4})\D+(\d{4})/',$year->academic_year,$years);
        if(!isset($years[2]))throw ValidationException::withMessages(['certificates'=>'The academic year must contain its start and end years, for example 2025-2026.']);
        $prefix=substr($years[2],-2);
        $pending=$eligible->whereNotIn('student_id',$existing->pluck('student_id'))->map(function($row)use(&$sequence,$prefix){
            if(++$sequence>9999)throw ValidationException::withMessages(['certificates'=>'The four-digit certificate sequence is full.']);
            return $row+['sequence'=>$sequence,'certificate_number'=>$prefix.str_pad((string)$sequence,4,'0',STR_PAD_LEFT)];
        })->values();
        return ['rows'=>$pending,'hash'=>hash('sha256',json_encode([$exam->published_at?->toISOString(),$exam->version,$this->settings($year)['version'],$eligible,$existing->map->only(['id','sequence','status']),$pending]))];
    }
    public function assign(User $user,AcademicYear $year,string $hash): int
    {
        $this->authorize($user,'certificate-assign');
        return DB::transaction(function()use($user,$year,$hash){
            AcademicYear::whereKey($year->id)->lockForUpdate()->firstOrFail();
            $exam=$this->requirePublished($year);ScholarshipExam::whereKey($exam->id)->lockForUpdate()->firstOrFail();
            $plan=$this->plan($year);if(!hash_equals($plan['hash'],$hash))abort(409,'The numbering preview changed. Preview the list again before assigning.');
            foreach($plan['rows'] as $row)ScholarshipCertificate::create(['academic_year_id'=>$year->id,'student_id'=>$row['student_id'],'campus_id'=>$row['campus_id'],
                'sequence'=>$row['sequence'],'certificate_number'=>$row['certificate_number'],'type'=>$row['type'],'snapshot'=>Arr::except($row,['sequence','certificate_number']),
                'status'=>'active','assigned_by'=>$user->id]);
            $this->event($user,$year,'certificate-assign',['count'=>$plan['rows']->count(),'numbers'=>$plan['rows']->pluck('certificate_number')->all()]);
            return $plan['rows']->count();
        },3);
    }
    public function cancel(User $user,ScholarshipCertificate $certificate,string $reason): void
    {
        $this->authorize($user,'certificate-cancel');
        DB::transaction(function()use($user,$certificate,$reason){
            $year=AcademicYear::findOrFail($certificate->academic_year_id);AcademicYear::whereKey($year->id)->lockForUpdate()->firstOrFail();
            $certificate=ScholarshipCertificate::whereKey($certificate->id)->lockForUpdate()->firstOrFail();
            if($certificate->status!=='active')abort(409,'This certificate is already cancelled.');
            $certificate->update(['status'=>'cancelled','cancel_reason'=>$reason,'cancelled_by'=>$user->id,'cancelled_at'=>now()]);
            $this->event($user,$year,'certificate-cancel',['certificate_number'=>$certificate->certificate_number,'reason'=>$reason]);
        });
    }
    public function values(array $snapshot,array $settings,string $number=''): array
    {
        $values=$snapshot;$values['certificate_number']=$number?:'PREVIEW';$values['issue_date']=\Carbon\Carbon::parse($settings['issue_date'])->format('F j, Y');
        $values['location']=$settings['location'];$values['scholarship_year']=$settings['scholarship_year'];
        foreach([0,1] as $i)foreach(['name','position'] as $key)$values['signer_'.($i+1).'_'.$key]=$settings['signers'][$i][$key]??'';
        return Arr::only($values,ScholarshipCertificateLayout::TOKENS);
    }
}
