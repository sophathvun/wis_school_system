<?php

namespace App\Services;

use App\Models\{AcademicYear, Grade, ScholarshipCandidate, ScholarshipCommittee, ScholarshipEvent, ScholarshipExam, ScholarshipScore, ScholarshipStudentRank, ScholarshipSubject, StudentEnrollment, User};
use App\Support\ScholarshipPermissions;
use App\Support\ScholarshipCandidateCode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ScholarshipWorkflow
{
    private array $permissionCache = [];
    private function superUser(User $user): bool { return $this->permissionCache[$user->id.':super'] ??= $user->isSuperAdmin(); }
    private function allowed(User $user, string $action): bool
    {
        return $this->permissionCache[$user->id.':'.$action] ??= ScholarshipPermissions::allows($user,$action);
    }
    public const ROLES = ['invigilator'=>'Invigilator','score-entry'=>'Score Entry','score-reviewer'=>'Score Reviewer','final-approver'=>'Final Approver'];

    private function fail(string $message): never { throw ValidationException::withMessages(['exam'=>$message]); }

    public function central(User $user, ScholarshipExam $exam, string $action): bool
    {
        if (!$exam->academicYear || $exam->academicYear->period_type!=='regular') return false;
        if ($this->superUser($user)) return true;
        if (empty($exam->campus_ids)) return ScholarshipPermissions::allows($user,$action);
        return collect($exam->campus_ids)->every(fn ($id) =>
            ScholarshipPermissions::allows($user, $action, (int) $id));
    }

    public function canView(User $user, ScholarshipExam $exam): bool
    {
        if (!ScholarshipPermissions::allows($user)) return false;
        if (!$exam->academicYear || $exam->academicYear->period_type!=='regular') return false;
        if ($this->superUser($user)) return true;
        if (empty($exam->campus_ids)) return true;
        return ScholarshipPermissions::campuses($user)->whereIn('tb_school_info.id', $exam->campus_ids)->get()->contains(fn ($campus) => ScholarshipPermissions::allows($user, 'view', $campus->id))
            || $exam->committee()->where('user_id', $user->id)->exists();
    }

    public function candidates(User $user, ScholarshipExam $exam)
    {
        abort_unless($this->canView($user, $exam), 403);
        $query = $exam->candidates();
        if ($this->superUser($user)) return $query;
        $campuses = ScholarshipPermissions::campuses($user)->get()->filter(fn ($campus) => ScholarshipPermissions::allows($user, 'view', $campus->id))->pluck('id');
        $members = $exam->committee()->where('user_id', $user->id)->get();
        return $query->where(function ($scope) use ($campuses, $members) {
            $scope->whereIn('campus_id', $campuses);
            foreach ($members as $member) {
                if (!$member->grade_number && !$member->room_number && !$member->exam_center_id) $scope->orWhereRaw('1 = 1');
                else $scope->orWhere(fn ($assigned) => $assigned
                    ->when($member->grade_number, fn ($q, $grade) => $q->where('grade_number', $grade))
                    ->when($member->exam_center_id, fn($q,$center)=>$q->where('exam_center_id',$center))
                    ->when($member->room_number, fn ($q, $room) => $q->where('room_number', $room)->when(!$member->exam_center_id,fn($q)=>$q->whereNull('exam_center_id'))));
            }
        });
    }

    public function canScore(User $user, ScholarshipExam $exam, ScholarshipCandidate $candidate, bool $review = false): bool
    {
        if($exam->isHistorical())return false;
        if (!$exam->academicYear || $exam->academicYear->period_type!=='regular') return false;
        if ($this->superUser($user)) return true;
        if (!$this->allowed($user, $review ? 'review-scores' : 'scoring')) return false;
        $exam->loadMissing('committee');
        return $exam->committee->contains(fn($member)=>$member->user_id===$user->id && $member->role===($review?'score-reviewer':'score-entry')
            && (!$member->exam_center_id || $member->exam_center_id===$candidate->exam_center_id)
            && (!$member->grade_number || $member->grade_number===$candidate->grade_number)
            && (!$member->room_number || ($member->room_number===$candidate->room_number && ($member->exam_center_id || !$candidate->exam_center_id))));
    }

    public function gradeNumber(Grade $grade): ?int
    {
        foreach ([$grade->grade_short_name, $grade->grade] as $label) {
            $label = strtr(trim((string) $label), array_combine(preg_split('//u','០១២៣៤៥៦៧៨៩',-1,PREG_SPLIT_NO_EMPTY), range(0,9)));
            if (preg_match('/^(?:grade\s*|g|ថ្នាក់ទី\s*)?(1[0-2]|[1-9])$/iu', $label, $match)) return (int) $match[1];
        }
        return null;
    }

    public function applicableSubjects(ScholarshipExam $exam, int $grade): Collection
    {
        return app(\App\Support\ScholarshipSubjectOrder::class)->forGrade($exam,$grade);
    }

    private function event(ScholarshipExam $exam, User $user, string $action, array $details = []): void
    {
        ScholarshipEvent::create(['exam_id'=>$exam->id,'user_id'=>$user->id,'action'=>$action,'details'=>$details]);
    }

    private function version(ScholarshipExam $exam, int $version): void
    {
        if ($exam->version !== $version) $this->fail('This exam changed in another window. Refresh before continuing.');
    }

    private function historicalReadOnly(ScholarshipExam $exam): void
    {
        if($exam->isHistorical())$this->fail('Historical scholarship records are protected. Use Import / Export to preview an authorized correction.');
    }

    private function editable(ScholarshipExam $exam): void
    {
        $this->historicalReadOnly($exam);
        if ($exam->seats_locked_at || $exam->published_at) $this->fail('This exam is locked. Reopen its seat plan before changing settings or candidates.');
    }

    public function saveExam(User $user, ?ScholarshipExam $exam, array $data): ScholarshipExam
    {
        return DB::transaction(function () use ($user, $exam, $data) {
            $year=AcademicYear::lockForUpdate()->findOrFail($data['academic_year_id']);
            if ($year->period_type!=='regular') $this->fail('Scholarship exams are available only for regular academic years.');
            $campusIds=ScholarshipStudentRank::whereHas('enrollment',fn($q)=>$q->where('academic_year_id',$year->id))->get()
                ->load('enrollment')->pluck('enrollment.campus_id')->unique()->map(fn($id)=>(int)$id)->sort()->values()->all();
            abort_unless(ScholarshipPermissions::allows($user,'settings'),403);
            foreach ($campusIds as $id) abort_unless(ScholarshipPermissions::allows($user,'settings',$id),403);
            if ($exam) {
                $exam = ScholarshipExam::lockForUpdate()->findOrFail($exam->id);
                abort_unless($this->central($user, $exam, 'settings'), 403);
                $this->version($exam, $data['version']); $this->editable($exam);
                if ($exam->academic_year_id !== $year->id) $this->fail('An exam belongs to its original academic year. Create a session for the other year.');
            }
            if (ScholarshipExam::where('academic_year_id',$year->id)->when($exam,fn($q)=>$q->whereKeyNot($exam->id))->exists()) $this->fail('A scholarship exam already exists for this academic year. Open its settings to make changes.');
            // A grade must not receive two subjects with the same name through overlapping ranges.
            $existingSubjects=$exam?$exam->subjects()->get()->keyBy('id'):collect();
            if($exam&&$exam->subjects()->whereHas('papers')->exists()){
                $sameSubjects=count($data['subjects'])===$existingSubjects->count()&&collect($data['subjects'])->every(function($subject)use($existingSubjects){
                    $existing=$existingSubjects->get((int)($subject['id']??0));
                    return $existing&&trim($subject['name'])===$existing->name&&(int)$subject['grade_from']===(int)$existing->grade_from
                        &&(int)$subject['grade_to']===(int)$existing->grade_to&&(float)$subject['max_score']===(float)$existing->max_score
                        &&(float)($subject['weight']??1)===(float)$existing->weight;
                });
                if(!$sameSubjects)$this->fail('This exam has answer-sheet papers linked to its subjects. Keep the subject IDs, names, grade ranges, maximum scores and weights unchanged when updating centers or seating settings.');
            }
            $subjectIds=collect($data['subjects'])->pluck('id')->filter()->map(fn($id)=>(int)$id)->values();
            if($subjectIds->unique()->count()!==$subjectIds->count()||$subjectIds->diff($existingSubjects->keys())->isNotEmpty())$this->fail('Choose each subject once from this exam.');
            $removedIds=$existingSubjects->keys()->diff($subjectIds);
            if(DB::table('tb_scholarship_score')->whereIn('subject_id',$removedIds)->exists()||DB::table('tb_scholarship_award')->where('exam_id',$exam?->id)->whereIn('slot',$removedIds->map(fn($id)=>'subject_'.$id))->exists())$this->fail('Subjects with saved scores or awards cannot be removed. Use Edit Exam Order to rearrange them.');
            for ($grade = 1; $grade <= 12; $grade++) {
                $names = collect($data['subjects'])->filter(fn ($s) => $s['grade_from'] <= $grade && $s['grade_to'] >= $grade)->map(fn ($s) => mb_strtolower(trim($s['name'])));
                if ($names->unique()->count() !== $names->count()) $this->fail('Subject names must be unique within each grade. Check overlapping grade ranges.');
            }
            $values = collect($data)->only(['academic_year_id','exam_date','room_capacity','examination_type'])->all();
            $values += ['name'=>$year->academic_year,'campus_ids'=>$campusIds,'eligible_ranks'=>[],'tie_policy'=>'competition'];
            $values['award_scheme'] = $data['award_scheme'] ?? ['total_1'=>100,'total_2'=>75,'total_3'=>50,'subject'=>25];
            if ($exam) $exam->fill($values)->forceFill(['version'=>$exam->version+1])->save();
            else $exam = ScholarshipExam::create($values + ['created_by'=>$user->id,'version'=>1]);
            app(ScholarshipExamCenters::class)->save($exam,$data,$campusIds);
            $savedIds=[];
            foreach(array_values($data['subjects']) as $position=>$subject){
                $record=!empty($subject['id'])?$existingSubjects->get((int)$subject['id']):$exam->subjects()->make();
                $timing=[];
                if(array_key_exists('starts_at',$subject)||array_key_exists('ends_at',$subject)||array_key_exists('exam_date',$subject))$timing=app(\App\Support\ScholarshipSubjectSchedule::class)->normalize($subject,'subjects.'.$position)??['exam_date'=>null,'starts_at'=>null,'ends_at'=>null];
                $record->fill(collect($subject)->only(['name','grade_from','grade_to','max_score','weight'])->all()+$timing+['position'=>$position])->save();$savedIds[]=$record->id;
            }
            $exam->subjects()->whereNotIn('id',$savedIds)->delete();$exam->unsetRelation('subjects');
            if($exam->subject_orders!==null){
                $order=app(\App\Support\ScholarshipSubjectOrder::class)->state($exam);
                foreach(['primary'=>[1,6],'secondary'=>[7,12]] as $band=>[$from,$to])$order['bands'][$band]=$exam->subjects->filter(fn($s)=>$s->grade_from<=$to&&$s->grade_to>=$from)->pluck('id')->map(fn($id)=>(int)$id)->all();
                $exam->forceFill(['subject_orders'=>$order])->save();
            }
            $overrides=$exam->subject_schedule_overrides;
            if($overrides!==null){
                foreach($overrides as $grade=>&$rows){$rows=array_filter($rows,fn($id)=>$exam->subjects->contains(fn($s)=>$s->id===(int)$id&&$s->grade_from<=$grade&&$s->grade_to>=$grade),ARRAY_FILTER_USE_KEY);}unset($rows);
                $exam->forceFill(['subject_schedule_overrides'=>array_filter($overrides)])->save();
            }
            if(collect($data['subjects'])->contains(fn($s)=>array_key_exists('starts_at',$s))){
                if($exam->subject_schedule_overrides===null)$exam->forceFill(['subject_schedule_overrides'=>[]])->save();
                app(\App\Support\ScholarshipSubjectSchedule::class)->ensureSessions($exam);
            }
            $this->synchronizeCandidates($exam);
            $this->event($exam, $user, 'settings-saved', ['version'=>$exam->version,'exam_centers'=>$exam->centers()->with('campuses')->get()->map(fn($center)=>['id'=>$center->id,'name'=>$center->name,'location'=>$center->location,'campus_ids'=>$center->campuses->pluck('id')->all()])->all()]);
            return $exam->fresh();
        });
    }

    /** Report wording may change after confirmation; allocation and scores remain locked. */
    public function saveExaminationType(User $user, ScholarshipExam $exam, string $title, int $version): ScholarshipExam
    {
        return DB::transaction(function()use($user,$exam,$title,$version){
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id);
            abort_unless($this->central($user,$exam,'settings'),403);
            $this->version($exam,$version);
            $title=trim($title);
            if($title===''||mb_strlen($title)>120)throw ValidationException::withMessages(['examination_type'=>'Enter an examination type of up to 120 characters.']);
            $before=$exam->examinationTitle();
            $exam->forceFill(['examination_type'=>$title,'version'=>$exam->version+1])->save();
            $this->event($exam,$user,'examination-type-updated',['before'=>$before,'after'=>$title]);
            return $exam->fresh();
        });
    }

    public function saveCenterLocations(User $user, ScholarshipExam $exam, array $locations, int $version): ScholarshipExam
    {
        return DB::transaction(function()use($user,$exam,$locations,$version){
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id);
            abort_unless($this->central($user,$exam,'settings'),403);
            $this->version($exam,$version);
            $centers=$exam->centers()->get()->keyBy('id');$changes=[];
            if(!$locations || count($locations)>50)throw ValidationException::withMessages(['locations'=>'Choose the exam center locations to update.']);
            foreach($locations as $id=>$location){
                if(!ctype_digit((string)$id)||!$centers->has($id))throw ValidationException::withMessages(['locations'=>'Choose exam centers belonging to this academic year.']);
                if(!is_string($location)||trim($location)===''||mb_strlen(trim($location))>80)throw ValidationException::withMessages(['locations.'.$id=>'Enter a location of up to 80 characters.']);
                $center=$centers->get($id);$changes[]=['id'=>$center->id,'before'=>$center->location,'after'=>trim($location)];
                $center->update(['location'=>trim($location)]);
            }
            $exam->forceFill(['version'=>$exam->version+1])->save();
            $this->event($exam,$user,'exam-center-locations-updated',['centers'=>$changes]);
            return $exam->fresh();
        });
    }

    public function saveSubjectOrder(User $user,ScholarshipExam $exam,array $bands,array $grades,int $version,?array $schedules=null): ScholarshipExam
    {
        return DB::transaction(function()use($user,$exam,$bands,$grades,$version,$schedules){
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id);
            abort_unless($this->central($user,$exam,'settings'),403);
            $this->version($exam,$version);
            $order=app(\App\Support\ScholarshipSubjectOrder::class)->validate($exam,$bands,$grades);
            $before=$exam->subject_orders;
            $beforeSchedule=$schedules!==null?app(\App\Support\ScholarshipSubjectSchedule::class)->state($exam):null;
            $exam->forceFill(['subject_orders'=>$order,'version'=>$exam->version+1])->save();
            if($schedules!==null){
                if($exam->isHistorical()||$exam->published_at)$this->fail('Published and historical subject schedules are read only.');
                app(\App\Support\ScholarshipSubjectSchedule::class)->save($exam,$schedules);
            }else app(ScholarshipInvigilators::class)->validateSchedules($exam);
            $this->event($exam,$user,'subject-exam-order-updated',['before'=>$before,'after'=>$order]+($schedules!==null?['schedule_before'=>$beforeSchedule,'schedule_after'=>app(\App\Support\ScholarshipSubjectSchedule::class)->state($exam)]:[]));
            return $exam->fresh();
        });
    }

    private function candidateValues(StudentEnrollment $enrollment, int $rank, ?float $eoyAverage=null): array
    {
        $enrollment->loadMissing(['student','academicYear','grade','schoolClass','schoolGroup','session','campus']);
        if (!$enrollment->academicYear || $enrollment->academicYear->period_type!=='regular') $this->fail('Scholarship candidates must belong to a regular academic year.');
        if (!$enrollment->student || !$enrollment->grade || !$enrollment->schoolClass || !$enrollment->campus) $this->fail('Complete the student, campus, grade, and class records before assigning a scholarship rank.');
        $number=$this->gradeNumber($enrollment->grade);
        if (!$number) $this->fail('Only Grades 1–12 can participate in the scholarship exam.');
        $student=$enrollment->student;
        if (blank($student->full_name_en)) $this->fail('An English student name is required for A–Z seat ordering. Complete the student profile first.');
        return ['student_id'=>$student->id,'campus_id'=>$enrollment->campus_id,'class_id'=>$enrollment->class_id,'group_id'=>$enrollment->group_id,
            'grade_number'=>$number,'class_rank'=>$rank,'snapshot'=>['student_code'=>$student->student_id ?: $student->student_no,'name_en'=>$student->full_name_en,'name_kh'=>$student->full_name_kh,'eoy_average'=>$eoyAverage,
                'campus'=>$enrollment->campus->campus_name_en ?: $enrollment->campus->campus_name_kh,
                'class'=>implode(' / ',array_filter([$enrollment->schoolClass->class_name,$enrollment->schoolGroup?->group_name,$enrollment->session?->session_name]))]];
    }

    /** Derived candidate records are refreshed inside the same transaction as ranks/settings. */
    private function synchronizeCandidates(ScholarshipExam $exam): void
    {
        $this->editable($exam);
        $ranks=ScholarshipStudentRank::with(['enrollment.student','enrollment.academicYear','enrollment.grade','enrollment.schoolClass','enrollment.schoolGroup','enrollment.session','enrollment.campus'])
            ->whereHas('enrollment',fn($q)=>$q->where('academic_year_id',$exam->academic_year_id))->orderBy('id')->get();
        $studentIds=[]; $campusIds=[];
        $values=$ranks->map(function($rank)use(&$studentIds,&$campusIds){
            $enrollment=$rank->enrollment;
            if (in_array($enrollment->student_id,$studentIds,true)) $this->fail('This student already has a scholarship rank through another enrollment in this academic year. Keep one ranked enrollment per student.');
            $studentIds[]=$enrollment->student_id; $campusIds[]=(int)$enrollment->campus_id;
            return ['enrollment_id'=>$enrollment->id,'values'=>$this->candidateValues($enrollment,$rank->rank,$rank->eoy_average)];
        });
        $exam->candidates()->where(fn($q)=>$q->whereNotIn('student_enrollment_id',$ranks->pluck('student_enrollment_id'))->orWhereNull('student_enrollment_id'))->delete();
        foreach($values as $row)$exam->candidates()->updateOrCreate(['student_enrollment_id'=>$row['enrollment_id']],$row['values']);
        $campusIds=array_values(array_unique($campusIds));sort($campusIds);
        $exam->forceFill(['campus_ids'=>$campusIds,'eligible_ranks'=>[],'tie_policy'=>'competition'])->save();
    }

    public function saveRank(User $user, StudentEnrollment $enrollment, int $rank, ?float $eoyAverage=null, bool $allowUpdate=true): ScholarshipStudentRank
    {
        abort_unless(ScholarshipPermissions::allows($user,'assign-rank',(int)$enrollment->campus_id),403);
        if(!in_array($rank,[1,2,3],true))$this->fail('Select class rank 1, 2, or 3.');
        if($eoyAverage!==null && (!is_finite($eoyAverage) || $eoyAverage<0 || $eoyAverage>100 || round($eoyAverage,2)!==$eoyAverage))$this->fail('Enter an EOY overall score average from 0 to 100 with at most two decimal places.');
        return DB::transaction(function()use($user,$enrollment,$rank,$eoyAverage,$allowUpdate){
            AcademicYear::lockForUpdate()->findOrFail($enrollment->academic_year_id);
            $this->candidateValues($enrollment,$rank);
            $exams=ScholarshipExam::where('academic_year_id',$enrollment->academic_year_id)->orderBy('id')->lockForUpdate()->get();
            foreach($exams as $exam)$this->editable($exam);
            $duplicate=ScholarshipStudentRank::where('student_enrollment_id','!=',$enrollment->id)->whereHas('enrollment',fn($q)=>$q->where('academic_year_id',$enrollment->academic_year_id)->where('student_id',$enrollment->student_id))->exists();
            if($duplicate || (!$allowUpdate && ScholarshipStudentRank::where('student_enrollment_id',$enrollment->id)->exists()))
                throw ValidationException::withMessages(['enrollment_id'=>'This student already has an assigned rank. Use Edit Rank in the list to make changes.']);
            if($eoyAverage!==null){
                $peers=ScholarshipStudentRank::where('student_enrollment_id','!=',$enrollment->id)->whereNotNull('eoy_average')
                    ->whereHas('enrollment',fn($q)=>$q->where('academic_year_id',$enrollment->academic_year_id)->where('campus_id',$enrollment->campus_id)
                        ->where('grade_id',$enrollment->grade_id)->where('class_id',$enrollment->class_id)
                        ->where('session_id',$enrollment->session_id)->where('group_id',$enrollment->group_id))->get();
                foreach($peers as $peer){
                    if(($peer->rank<$rank && $peer->eoy_average<=$eoyAverage) || ($peer->rank>$rank && $peer->eoy_average>=$eoyAverage))
                        throw ValidationException::withMessages(['eoy_average'=>sprintf('Check EOY Avg.: Rank 1 must be higher than Rank 2, and Rank 2 higher than Rank 3 in the same class and group. Rank %d currently has %.2f.',$peer->rank,$peer->eoy_average)]);
                }
            }
            $record=ScholarshipStudentRank::updateOrCreate(['student_enrollment_id'=>$enrollment->id],['rank'=>$rank,'eoy_average'=>$eoyAverage,'assigned_by'=>$user->id]);
            foreach($exams as $exam){
                $this->synchronizeCandidates($exam);$exam->increment('version');
                $this->event($exam,$user,'class-rank-saved',['enrollment_id'=>$enrollment->id,'rank'=>$rank,'eoy_average'=>$eoyAverage,'candidate_count'=>$exam->candidates()->count()]);
            }
            return $record;
        });
    }

    public function removeRank(User $user, ScholarshipStudentRank $rank): void
    {
        $enrollment=$rank->enrollment;abort_unless($enrollment,404);
        abort_unless(ScholarshipPermissions::allows($user,'assign-rank',(int)$enrollment->campus_id),403);
        DB::transaction(function()use($user,$rank,$enrollment){
            $year=AcademicYear::lockForUpdate()->findOrFail($enrollment->academic_year_id);
            if($year->period_type!=='regular')$this->fail('Scholarship is available only for regular academic years.');
            $exams=ScholarshipExam::where('academic_year_id',$year->id)->orderBy('id')->lockForUpdate()->get();
            foreach($exams as $exam)$this->editable($exam);
            $rank->delete();
            foreach($exams as $exam){
                $this->synchronizeCandidates($exam);$exam->increment('version');
                $this->event($exam,$user,'class-rank-removed',['enrollment_id'=>$enrollment->id,'candidate_count'=>$exam->candidates()->count()]);
            }
        });
    }

    /** Each center starts at Room 1 / Seat 1; seat numbers continue through all grades and rooms. */
    public function allocate(Collection $candidates, int $capacity, string $academicYear, ?Collection $centers=null): array
    {
        if ($capacity < 1) $this->fail('Room capacity must be positive.');
        $yearPrefix=ScholarshipCandidateCode::yearPrefix($academicYear);
        if($yearPrefix===null)$this->fail('Use an academic year such as 2025-2026 before generating candidate numbers.');
        $compare = function ($a,$b) {
            return ($a->grade_number <=> $b->grade_number)
                ?: strcmp(mb_strtoupper(trim($a->snapshot['name_en'])),mb_strtoupper(trim($b->snapshot['name_en'])))
                ?: strcmp((string)($a->snapshot['student_code']??''),(string)($b->snapshot['student_code']??'')) ?: ($a->id <=> $b->id);
        };
        $sorted=$candidates->sort($compare);$gradeCounts=[];$numbers=[];$campusCenters=[];
        foreach($sorted as $key=>$candidate){$gradeCounts[$candidate->grade_number]=($gradeCounts[$candidate->grade_number]??0)+1;$numbers[$key]=$gradeCounts[$candidate->grade_number];}
        foreach($centers??collect() as $position=>$center)foreach($center->campuses as $campus)$campusCenters[$campus->id]=['id'=>$center->id,'name'=>$center->name,'position'=>$position];
        if($centers?->isNotEmpty())$sorted=$sorted->sort(fn($a,$b)=>(($campusCenters[$a->campus_id]['position']??PHP_INT_MAX)<=>($campusCenters[$b->campus_id]['position']??PHP_INT_MAX))?:$compare($a,$b));
        $plan=[]; $room=0; $grade=null; $seat=0; $roomCount=0; $previousCenter=null;
        foreach ($sorted as $key=>$candidate) {
            $center=$campusCenters[$candidate->campus_id]??null;
            if($previousCenter!==($center['id']??null)){$room=0;$seat=0;$roomCount=0;$grade=null;}
            if ($grade !== $candidate->grade_number) { $grade=$candidate->grade_number; $room++; $roomCount=0; }
            elseif ($roomCount === $capacity) { $room++; $roomCount=0; }
            $roomCount++;
            $previousCenter=$center['id']??null;$number=$numbers[$key];
            $plan[]=['id'=>$candidate->id,'grade_number'=>$grade,'room_number'=>$room,'room_seat'=>++$seat,'grade_exam_number'=>$number,
                'exam_center_id'=>$center['id']??null,'exam_center_name'=>$center['name']??null,'seat_scope'=>$center['id']??0,
                'candidate_code'=>sprintf('%s-G%02d-%03d',$yearPrefix,$grade,$number),'snapshot'=>$candidate->snapshot];
        }
        return $plan;
    }

    public function plan(ScholarshipExam $exam): array
    {
        if($exam->isHistorical())return ['rows'=>$this->orderedSeats($exam,$exam->candidates()->get())->map->toArray()->all(),'unassigned_campuses'=>[],'token'=>''];
        $centers=$exam->centers()->with('campuses')->get();
        $rows = $this->allocate($exam->candidates()->get(),$exam->room_capacity,(string)$exam->academicYear?->academic_year,$centers);
        $unassigned=$centers->isEmpty()?[]:collect($rows)->whereNull('exam_center_id')->pluck('snapshot.campus')->unique()->values()->all();
        return ['rows'=>$rows,'unassigned_campuses'=>$unassigned,'token'=>hash('sha256',json_encode([$exam->id,$exam->version,$exam->room_capacity,$rows],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))];
    }

    public function orderedSeats(ScholarshipExam $exam, Collection $candidates): Collection
    {
        $exam->loadMissing('centers');$positions=$exam->centers->pluck('position','id');
        return $candidates->sort(fn($a,$b)=>(($positions[$a->exam_center_id]??PHP_INT_MAX)<=>($positions[$b->exam_center_id]??PHP_INT_MAX))
            ?:($a->room_number<=>$b->room_number)?:($a->room_seat<=>$b->room_seat)?:($a->id<=>$b->id))->values();
    }

    public function lockPlan(User $user, ScholarshipExam $exam, string $token): void
    {
        DB::transaction(function () use ($user,$exam,$token) {
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id); abort_unless($this->central($user,$exam,'seat-plan'),403); $this->editable($exam);
            $plan=$this->plan($exam);
            if($plan['unassigned_campuses'])$this->fail('Assign these campuses to an exam center in Scholarship Settings before confirming seats: '.implode(', ',$plan['unassigned_campuses']).'.');
            if (!$plan['rows']) $this->fail('Select candidates before confirming a seat plan.');
            foreach (collect($plan['rows'])->pluck('grade_number')->unique() as $grade) {
                if ($this->applicableSubjects($exam,$grade)->isEmpty()) $this->fail('Configure exam subjects for Grade '.$grade.' before confirming seats.');
            }
            if (!hash_equals($plan['token'],$token)) $this->fail('The preview changed. Refresh and review the seat plan before confirming.');
            foreach ($plan['rows'] as $row) $exam->candidates()->whereKey($row['id'])->update(collect($row)->only(['room_number','room_seat','grade_exam_number','candidate_code','exam_center_id','exam_center_name','seat_scope'])->all());
            $exam->forceFill(['seats_locked_at'=>now(),'version'=>$exam->version+1])->save(); $this->event($exam,$user,'seat-plan-confirmed',['candidates'=>count($plan['rows']),'capacity'=>$exam->room_capacity]);
        });
    }

    public function reopenPlan(User $user, ScholarshipExam $exam, int $version, string $reason): void
    {
        DB::transaction(function () use ($user,$exam,$version,$reason) {
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id); $this->historicalReadOnly($exam); abort_unless($this->central($user,$exam,'seat-plan'),403); $this->version($exam,$version);
            if ($exam->published_at || ScholarshipScore::whereIn('candidate_id',$exam->candidates()->select('id'))->exists() || $exam->candidates()->whereNotNull('reviewed_at')->exists()) $this->fail('A seat plan cannot be reopened after scoring or review has started. Scores and results are protected.');
            $roomDuties=$exam->committee()->whereNotNull('room_number')->get();
            $exam->committee()->whereNotNull('room_number')->delete();
            $invigilatorDuties=$exam->invigilatorDuties()->get();
            $exam->invigilatorDuties()->delete();
            $exam->candidates()->update(['room_number'=>null,'room_seat'=>null,'grade_exam_number'=>null,'candidate_code'=>null,'exam_center_id'=>null,'exam_center_name'=>null,'seat_scope'=>0]);
            $exam->forceFill(['seats_locked_at'=>null,'version'=>$exam->version+1])->save(); $this->event($exam,$user,'seat-plan-reopened',['reason'=>$reason,'cleared_room_duties'=>$roomDuties->map->only(['id','user_id','role','grade_number','room_number','exam_center_id'])->all(),'cleared_invigilator_duties'=>$invigilatorDuties->toArray()]);
        });
    }

    public function calculate(ScholarshipExam $exam, ScholarshipCandidate $candidate): array
    {
        $subjects=$this->applicableSubjects($exam,$candidate->grade_number); $scores=$candidate->scores->keyBy('subject_id');
        $complete=$subjects->isNotEmpty(); $total=0; $weighted=0; $weights=0;$absentSubjects=[];
        foreach ($subjects as $subject) {
            $absent=$candidate->absent || $scores->get($subject->id)?->is_absent;
            if($absent)$absentSubjects[]=$subject->id;
            $score=$absent?0:$scores->get($subject->id)?->score;
            if ($score === null) { $complete=false; continue; }
            $total+=$score; $weighted+=($score/$subject->max_score)*$subject->weight; $weights+=$subject->weight;
        }
        $allAbsent=$subjects->isNotEmpty() && count($absentSubjects)===$subjects->count();
        return ['complete'=>$complete,'total'=>$complete?round($total,2):null,'percentage'=>$complete&&$weights>0?round($weighted/$weights*100,4):null,
            'absent_subjects'=>$absentSubjects,'all_absent'=>$allAbsent,'overall_eligible'=>!$absentSubjects,
            'max_total'=>$subjects->sum('max_score'),'status'=>$allAbsent?'Absent — All Subjects':($complete?($absentSubjects?'Partially Absent':($candidate->reviewed_at?'Reviewed':'Awaiting review')):'Incomplete')];
    }

    public function saveScoreDrafts(User $user, ScholarshipExam $exam, array $rows): void
    {
        DB::transaction(function()use($user,$exam,$rows){
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id);
            foreach($rows as $candidateId=>$row){
                if(!preg_match('/^[1-9]\d*$/',(string)$candidateId))$this->fail('Select valid students before saving score drafts.');
                $candidate=$exam->candidates()->findOrFail($candidateId);
                try{
                    $this->saveScores($user,$exam,$candidate,$row['scores']??[],(bool)($row['absent']??false),(int)$row['version'],true,$row['subject_absences']??[]);
                }catch(ValidationException $error){
                    $messages=[];
                    foreach($error->errors() as $field=>$errors)$messages['rows.'.$candidateId.'.'.$field]=array_map(fn($message)=>($candidate->snapshot['name_en']??$candidate->candidate_code).': '.$message,$errors);
                    throw ValidationException::withMessages($messages);
                }
            }
        });
    }

    public function saveScores(User $user, ScholarshipExam $exam, ScholarshipCandidate $candidate, array $scores, bool $absent, int $version, bool $skipUnchanged=false, array $subjectAbsences=[]): void
    {
        DB::transaction(function () use ($user,$exam,$candidate,$scores,$absent,$version,$skipUnchanged,$subjectAbsences) {
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id);
            $candidate=ScholarshipCandidate::lockForUpdate()->findOrFail($candidate->id);
            abort_unless($candidate->exam_id===$exam->id && $this->canScore($user,$exam,$candidate),403);
            if (!$exam->seats_locked_at || $exam->published_at) $this->fail('Confirm seats before scoring. Published scores cannot be changed until results are explicitly reopened.');
            if ($candidate->version !== $version) $this->fail('These scores changed in another window. Refresh before saving.');
            $subjects=$this->applicableSubjects($exam,$candidate->grade_number);
            if ($subjects->isEmpty()) $this->fail('Configure subjects for this grade before scoring.');
            if (array_diff(array_map('strval',array_keys($scores)),$subjects->pluck('id')->map(fn($id)=>(string)$id)->all())) $this->fail('A score was submitted for a subject outside this grade.');
            if(array_diff(array_map('strval',array_keys($subjectAbsences)),$subjects->pluck('id')->map(fn($id)=>(string)$id)->all()))$this->fail('Attendance was submitted for a subject outside this grade.');
            foreach($subjectAbsences as $value)if(!in_array($value,[true,false,0,1,'0','1'],true))$this->fail('Subject attendance must be present or absent.');
            $previousScores=$candidate->scores()->get();
            $beforeAbsences=$previousScores->pluck('is_absent','subject_id')->all();$beforeAbsent=$candidate->absent;
            $before=$previousScores->pluck('score','subject_id')->all();
            $beforeSources=$previousScores->keyBy('subject_id')->map(fn($score)=>$score->only(['entry_method','source_paper_id','source_reference']))->all();
            $normalized=[];$normalizedAbsences=[];
            foreach ($subjects as $subject) {
                $subjectAbsent=$absent || (bool)($subjectAbsences[$subject->id]??$beforeAbsences[$subject->id]??false);
                $value=$subjectAbsent?0:($scores[$subject->id]??null);
                if ($value==='') $value=null;
                if ($value!==null && (!is_numeric($value) || !is_finite((float)$value) || $value<0 || $value>$subject->max_score || round((float)$value,2)!=(float)$value)) {
                    throw ValidationException::withMessages(['scores.'.$subject->id=>'Enter a score from 0 to '.$subject->max_score.' with at most two decimal places.']);
                }
                $normalized[$subject->id]=$value;
                $normalizedAbsences[$subject->id]=$subjectAbsent;
            }
            $absent=collect($normalizedAbsences)->every(fn($value)=>$value);
            // Saving a page must not clear reviews, imported-score provenance or awards on unchanged rows.
            if($skipUnchanged && $candidate->absent===$absent && $subjects->every(function($subject)use($normalized,$before,$normalizedAbsences,$beforeAbsences){
                $value=$normalized[$subject->id];$previous=$before[$subject->id]??null;
                return $normalizedAbsences[$subject->id]===(bool)($beforeAbsences[$subject->id]??false) && ($value===null?$previous===null:($previous!==null&&(float)$previous===(float)$value));
            }))return;
            foreach($normalized as $subjectId=>$value)ScholarshipScore::updateOrCreate(['candidate_id'=>$candidate->id,'subject_id'=>$subjectId],['score'=>$value,'is_absent'=>$normalizedAbsences[$subjectId],'entry_method'=>'manual','source_paper_id'=>null,'source_reference'=>null]);
            $candidate->forceFill(['absent'=>$absent,'scored_by'=>$user->id,'reviewed_by'=>null,'reviewed_at'=>null,'version'=>$candidate->version+1])->save();
            if ($exam->awards()->where('grade_number',$candidate->grade_number)->exists()) {
                $exam->awards()->where('grade_number',$candidate->grade_number)->delete();
                $this->event($exam,$user,'awards-cleared',['grade'=>$candidate->grade_number,'reason'=>'Scores changed; select winners again after review.']);
            }
            $exam->increment('version');
            $this->event($exam,$user,'scores-saved',['candidate_id'=>$candidate->id,'before'=>$before,'before_absent'=>$beforeAbsent,'before_subject_absences'=>$beforeAbsences,'before_sources'=>$beforeSources,'entry_method'=>'manual','scores'=>$normalized,'absent'=>$absent,'subject_absences'=>$normalizedAbsences]);
        });
    }

    public function reviewScores(User $user, ScholarshipExam $exam, array $rows): void
    {
        DB::transaction(function()use($user,$exam,$rows){
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id);
            foreach($rows as $candidateId=>$row){
                if(!preg_match('/^[1-9]\d*$/',(string)$candidateId))$this->fail('Select valid students before reviewing scores.');
                $candidate=$exam->candidates()->findOrFail($candidateId);
                try{
                    $this->review($user,$exam,$candidate,(int)$row['version']);
                }catch(ValidationException $error){
                    $messages=[];
                    foreach($error->errors() as $field=>$errors)$messages['rows.'.$candidateId.'.'.$field]=array_map(fn($message)=>($candidate->snapshot['name_en']??$candidate->candidate_code).': '.$message,$errors);
                    throw ValidationException::withMessages($messages);
                }
            }
        });
    }

    public function review(User $user, ScholarshipExam $exam, ScholarshipCandidate $candidate, int $version): void
    {
        DB::transaction(function () use ($user,$exam,$candidate,$version) {
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id); $candidate=ScholarshipCandidate::lockForUpdate()->findOrFail($candidate->id);
            abort_unless($candidate->exam_id===$exam->id && $this->canScore($user,$exam,$candidate,true),403);
            if (!$exam->seats_locked_at || $exam->published_at) $this->fail('Only an unpublished exam with confirmed seats can be reviewed.');
            if ($candidate->version!==$version) $this->fail('These scores changed. Refresh before reviewing.');
            if (!$this->calculate($exam,$candidate)['complete']) $this->fail('Complete every subject score, or mark the candidate absent, before reviewing.');
            ScholarshipAnswerSheets::assertCurrentScanKeys($candidate);
            $candidate->forceFill(['reviewed_by'=>$user->id,'reviewed_at'=>now(),'version'=>$candidate->version+1])->save();
            $this->event($exam,$user,'scores-reviewed',['candidate_id'=>$candidate->id]);
        });
    }

    public function results(ScholarshipExam $exam, ?Collection $candidates = null): Collection
    {
        $candidates??=$exam->candidates()->with('scores')->get();
        if($exam->isHistorical())return $candidates->map(function($candidate){
            $category=$candidate->snapshot['historical_award_category']??'';
            $label=match($category){'total_1'=>'1st Place','total_2'=>'2nd Place','total_3'=>'3rd Place','subject'=>'Best in '.($candidate->snapshot['historical_award_subject']??''),default=>$category};
            $absentSubjects=$candidate->scores->filter(fn($score)=>$score->is_absent)->pluck('subject_id')->all();
            return ['candidate'=>$candidate,'complete'=>$candidate->result_total!==null||$candidate->absent,'total'=>$candidate->absent?0.0:$candidate->result_total,'percentage'=>$candidate->absent?0.0:$candidate->result_percentage,'max_total'=>null,
                'absent_subjects'=>$absentSubjects,'all_absent'=>$candidate->absent,'overall_eligible'=>!$candidate->absent&&!$absentSubjects,
                'status'=>$candidate->absent?'Historical absence':($absentSubjects?'Historical partial absence':($candidate->result_total!==null?'Historical result':'Historical record')),'rank'=>$candidate->absent?null:$candidate->result_rank,'award'=>$candidate->award_percent,'award_label'=>$label];
        })->sort(fn($a,$b)=>($a['candidate']->grade_number<=>$b['candidate']->grade_number)?:($a['rank']??PHP_INT_MAX)<=>($b['rank']??PHP_INT_MAX))->values();
        $awards=$exam->awards()->whereNotNull('candidate_id')->get()->keyBy('candidate_id');
        $rows=$candidates->map(fn($candidate)=>['candidate'=>$candidate]+$this->calculate($exam,$candidate)+['rank'=>null,'award'=>$awards->get($candidate->id)?->award_percent,'award_label'=>$awards->has($candidate->id)?$this->awardSlots($exam,$candidate->grade_number)->firstWhere('key',$awards[$candidate->id]->slot)['label']:null]);
        foreach ($rows->groupBy(fn($row)=>$row['candidate']->grade_number,true) as $gradeRows) {
            $ranked=$gradeRows->filter(fn($row)=>$row['complete'] && $row['overall_eligible'])->sort(fn($a,$b)=>($b['total']<=>$a['total'])?:($a['candidate']->grade_exam_number<=>$b['candidate']->grade_exam_number));
            $position=0; $previous=null; $rank=0;
            foreach ($ranked as $key=>$row) {
                $position++;
                if ($previous===null || $previous!==$row['total']) $rank=$position;
                $previous=$row['total'];
                $rows[$key]=array_replace($row,['rank'=>$rank]);
            }
        }
        return $rows->sort(fn($a,$b)=>($a['candidate']->grade_number<=>$b['candidate']->grade_number)?:($a['rank']??PHP_INT_MAX)<=>($b['rank']??PHP_INT_MAX)?:($a['candidate']->grade_exam_number<=>$b['candidate']->grade_exam_number))->values();
    }

    public function awardSlots(ScholarshipExam $exam, int $grade): Collection
    {
        $scheme=$exam->awardScheme();
        $slots=collect([1=>'1st Place — Total Score',2=>'2nd Place — Total Score',3=>'3rd Place — Total Score'])
            ->map(fn($label,$place)=>['key'=>'total_'.$place,'label'=>$label,'subject_id'=>null,'percent'=>$scheme['total_'.$place]])->values();
        foreach ($this->applicableSubjects($exam,$grade) as $subject) $slots->push(['key'=>'subject_'.$subject->id,'label'=>'Best in '.$subject->name,'subject_id'=>$subject->id,'percent'=>$scheme['subject']]);
        return $slots;
    }

    private function validateAwards(ScholarshipExam $exam, int $grade, array $choices, Collection $rows): void
    {
        $slots=$this->awardSlots($exam,$grade);
        if (array_diff(array_keys($choices),$slots->pluck('key')->all()) || array_diff($slots->pluck('key')->all(),array_keys($choices))) $this->fail('Submit every award slot for this grade. Refresh the results first.');
        if ($rows->isEmpty() || $rows->contains(fn($row)=>!$row['complete'] || !$row['candidate']->reviewed_at)) $this->fail('Complete and review every candidate in this grade before selecting winners.');
        $remaining=$rows->reject(fn($row)=>$row['all_absent'])->keyBy(fn($row)=>$row['candidate']->id);
        foreach ($slots as $slot) {
            $choice=$choices[$slot['key']]; $id=$choice['candidate_id']??null;
            $percent=$choice['award_percent']??null;
            if (!is_numeric($percent) || !is_finite((float)$percent) || $percent<0 || $percent>100 || round((float)$percent,2)!=(float)$percent) $this->fail('Scholarship percentages must be from 0 to 100 with at most two decimal places.');
            $eligible=$remaining->filter(fn($row)=>$slot['subject_id']?!in_array($slot['subject_id'],$row['absent_subjects'],true):$row['overall_eligible']);
            if (!$id) {
                if ($eligible->isNotEmpty()) $this->fail('Choose a student for '.$slot['label'].'. Leave a slot vacant only when no eligible students remain.');
                continue;
            }
            if (!$eligible->has($id)) $this->fail('Each winner must attend the award subject (all subjects for overall places), be reviewed, and receive only one award.');
            $score=fn($row)=>$slot['subject_id']?$row['candidate']->scores->firstWhere('subject_id',$slot['subject_id'])->score:$row['total'];
            $highest=$eligible->map($score)->max();
            if ($score($remaining[$id])<$highest && mb_strlen(trim($choice['reason']??''))<5) $this->fail($slot['label'].': choose a student with the highest remaining score, or enter a reason of at least 5 characters for an exception.');
            $remaining->forget($id);
        }
    }

    public function saveAwards(User $user, ScholarshipExam $exam, int $grade, array $choices, int $version): void
    {
        DB::transaction(function () use ($user,$exam,$grade,$choices,$version) {
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id); $this->historicalReadOnly($exam);
            abort_unless($this->central($user,$exam,'awards'),403); $this->version($exam,$version);
            if (!$exam->seats_locked_at || $exam->published_at) $this->fail('Select winners after seats are confirmed. Reopen published results before editing awards.');
            $rows=$this->results($exam)->filter(fn($row)=>$row['candidate']->grade_number===$grade);
            $this->validateAwards($exam,$grade,$choices,$rows);
            $before=$exam->awards()->where('grade_number',$grade)->get()->map->only(['slot','candidate_id','award_percent','reason'])->all();
            $exam->awards()->where('grade_number',$grade)->delete();
            foreach ($choices as $slot=>$choice) $exam->awards()->create(['grade_number'=>$grade,'slot'=>$slot,'candidate_id'=>($choice['candidate_id']??null)?:null,'award_percent'=>$choice['award_percent'],'reason'=>trim($choice['reason']??'')?:null]);
            $exam->increment('version');
            $this->event($exam,$user,'awards-selected',['grade'=>$grade,'before'=>$before,'choices'=>$choices]);
        });
    }

    private function canPublish(User $user, ScholarshipExam $exam): bool
    {
        return $this->central($user,$exam,'publish-results') && ($this->superUser($user) || $exam->committee()->where('user_id',$user->id)->where('role','final-approver')->exists());
    }

    public function publish(User $user, ScholarshipExam $exam, int $version): void
    {
        DB::transaction(function () use ($user,$exam,$version) {
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id); $this->historicalReadOnly($exam); abort_unless($this->canPublish($user,$exam),403); $this->version($exam,$version);
            if ($exam->published_at || !$exam->seats_locked_at) $this->fail('Confirm the seat plan before publishing unpublished results.');
            $rows=$this->results($exam);
            if ($rows->isEmpty() || $rows->contains(fn($row)=>!$row['complete'] || !$row['candidate']->reviewed_at)) $this->fail('Every candidate must have complete, reviewed scores or a reviewed absence before publishing.');
            foreach($rows as $row)ScholarshipAnswerSheets::assertCurrentScanKeys($row['candidate']);
            foreach ($rows->groupBy(fn($row)=>$row['candidate']->grade_number) as $grade=>$gradeRows) {
                $saved=$exam->awards()->where('grade_number',$grade)->get()->keyBy('slot');
                if ($saved->count()!==$this->awardSlots($exam,$grade)->count()) $this->fail('Save the scholarship winners for Grade '.$grade.' before publishing.');
                $this->validateAwards($exam,(int)$grade,$saved->map(fn($award)=>$award->only(['candidate_id','award_percent','reason']))->all(),$gradeRows);
            }
            foreach ($rows as $row) $row['candidate']->forceFill(['result_total'=>$row['total'],'result_percentage'=>$row['percentage'],'result_rank'=>$row['rank'],'award_percent'=>$row['award']])->save();
            $exam->forceFill(['published_at'=>now(),'version'=>$exam->version+1])->save(); $this->event($exam,$user,'results-published',['candidates'=>$rows->count()]);
        });
    }

    public function reopenResults(User $user, ScholarshipExam $exam, int $version, string $reason): void
    {
        DB::transaction(function () use ($user,$exam,$version,$reason) {
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id); $this->historicalReadOnly($exam); abort_unless($this->canPublish($user,$exam),403); $this->version($exam,$version);
            if (!$exam->published_at) $this->fail('These results are already unpublished.');
            $exam->candidates()->update(['result_total'=>null,'result_percentage'=>null,'result_rank'=>null,'award_percent'=>null]);
            $exam->forceFill(['published_at'=>null,'version'=>$exam->version+1])->save(); $this->event($exam,$user,'results-reopened',['reason'=>$reason]);
        });
    }

    public function addCommittee(User $user, ScholarshipExam $exam, array $data): void
    {
        DB::transaction(function () use ($user,$exam,$data) {
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id); $this->historicalReadOnly($exam); abort_unless($this->central($user,$exam,'committee'),403);
            if ($exam->published_at) $this->fail('Reopen published results before changing the committee.');
            $data+=['grade_number'=>null,'room_number'=>null,'exam_center_id'=>null];
            foreach(['grade_number','room_number','exam_center_id'] as $field)$data[$field]=filled($data[$field])?(int)$data[$field]:null;
            if ($data['role']==='final-approver') { $data['grade_number']=null; $data['room_number']=null; $data['exam_center_id']=null; }
            if($data['exam_center_id'] && !$exam->centers()->whereKey($data['exam_center_id'])->exists())throw ValidationException::withMessages(['exam_center_id'=>'Select an exam center belonging to this exam.']);
            if($data['room_number']){
                if(!$exam->seats_locked_at)throw ValidationException::withMessages(['room_number'=>'Confirm the seat plan before assigning room duties.']);
                if($exam->centers()->exists() && !$data['exam_center_id'])throw ValidationException::withMessages(['exam_center_id'=>'Select an exam center before choosing a room.']);
                if(!$exam->candidates()->where('room_number',$data['room_number'])->where('exam_center_id',$data['exam_center_id'])->when($data['grade_number'],fn($q,$grade)=>$q->where('grade_number',$grade))->exists())throw ValidationException::withMessages(['room_number'=>'Choose a confirmed room matching the exam center and grade.']);
            }
            $member=$exam->committee()->firstOrCreate(collect($data)->only(['user_id','role','grade_number','room_number','exam_center_id'])->all());
            $this->event($exam,$user,'committee-assigned',['member_id'=>$member->id]+$data);
        });
    }

    public function removeCommittee(User $user, ScholarshipExam $exam, ScholarshipCommittee $member): void
    {
        DB::transaction(function () use ($user,$exam,$member) {
            $exam=ScholarshipExam::lockForUpdate()->findOrFail($exam->id); $this->historicalReadOnly($exam); abort_unless($this->central($user,$exam,'committee') && $member->exam_id===$exam->id,403);
            if ($exam->published_at) $this->fail('Reopen published results before changing the committee.');
            $this->event($exam,$user,'committee-removed',$member->only(['user_id','role','grade_number','room_number','exam_center_id'])); $member->delete();
        });
    }
}
