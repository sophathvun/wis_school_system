<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScholarshipExam extends Model
{
    protected $table = 'tb_scholarship_exam';
    protected $guarded = ['id'];
    protected $casts = ['campus_ids'=>'array','eligible_ranks'=>'array','award_rules'=>'array','award_scheme'=>'array','exam_date'=>'date','seats_locked_at'=>'datetime','published_at'=>'datetime','historical_imported_at'=>'datetime','historical_imported_by'=>'integer','version'=>'integer','room_capacity'=>'integer','academic_year_id'=>'integer'];
    public function examinationTitle(): string { return trim((string)$this->examination_type) ?: 'WIS Scholarship Exams'; }
    protected function casts(): array { return ['subject_orders'=>'array','subject_schedule_overrides'=>'array']; }
    public function isHistorical(): bool { return $this->historical_imported_at !== null; }
    public function awards() { return $this->hasMany(ScholarshipAward::class, 'exam_id'); }
    public function getNameAttribute($value): string { return $this->academicYear?->academic_year ?? (string)$value; }
    public function awardScheme(): array { return array_replace(['total_1'=>100,'total_2'=>75,'total_3'=>50,'subject'=>25], $this->award_scheme ?? []); }
    public function academicYear() { return $this->belongsTo(AcademicYear::class)->withTrashed(); }
    public function candidates() { return $this->hasMany(ScholarshipCandidate::class, 'exam_id'); }
    public function centers() { return $this->hasMany(ScholarshipExamCenter::class,'exam_id')->orderBy('position')->orderBy('id'); }
    public function subjects() { return $this->hasMany(ScholarshipSubject::class, 'exam_id')->orderBy('position')->orderBy('id'); }
    public function committee() { return $this->hasMany(ScholarshipCommittee::class, 'exam_id'); }
    public function invigilators() { return $this->hasMany(ScholarshipInvigilator::class,'exam_id')->orderBy('name_en')->orderBy('id'); }
    public function examSessions() { return $this->hasMany(ScholarshipExamSession::class,'exam_id')->orderBy('number'); }
    public function invigilatorDuties() { return $this->hasMany(ScholarshipInvigilatorDuty::class,'exam_id'); }
    public function sessionLinks() { return $this->hasMany(ScholarshipSessionLink::class,'exam_id'); }
    public function events() { return $this->hasMany(ScholarshipEvent::class, 'exam_id')->latest('id'); }
}
