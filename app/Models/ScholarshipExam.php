<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScholarshipExam extends Model
{
    protected $table = 'tb_scholarship_exam';
    protected $guarded = ['id'];
    protected $casts = ['campus_ids'=>'array','eligible_ranks'=>'array','award_rules'=>'array','award_scheme'=>'array','exam_date'=>'date','seats_locked_at'=>'datetime','published_at'=>'datetime','version'=>'integer','room_capacity'=>'integer','academic_year_id'=>'integer'];
    public function awards() { return $this->hasMany(ScholarshipAward::class, 'exam_id'); }
    public function getNameAttribute($value): string { return $this->academicYear?->academic_year ?? (string)$value; }
    public function awardScheme(): array { return array_replace(['total_1'=>100,'total_2'=>75,'total_3'=>50,'subject'=>25], $this->award_scheme ?? []); }
    public function academicYear() { return $this->belongsTo(AcademicYear::class)->withTrashed(); }
    public function candidates() { return $this->hasMany(ScholarshipCandidate::class, 'exam_id'); }
    public function centers() { return $this->hasMany(ScholarshipExamCenter::class,'exam_id')->orderBy('position')->orderBy('id'); }
    public function subjects() { return $this->hasMany(ScholarshipSubject::class, 'exam_id')->orderBy('position')->orderBy('id'); }
    public function committee() { return $this->hasMany(ScholarshipCommittee::class, 'exam_id'); }
    public function events() { return $this->hasMany(ScholarshipEvent::class, 'exam_id')->latest('id'); }
}
