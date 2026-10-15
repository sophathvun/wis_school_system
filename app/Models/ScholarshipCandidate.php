<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScholarshipCandidate extends Model
{
    protected $table = 'tb_scholarship_candidate';
    protected $guarded = ['id'];
    protected $casts = ['snapshot'=>'array','absent'=>'boolean','reviewed_at'=>'datetime','version'=>'integer','result_total'=>'float','result_percentage'=>'float','award_percent'=>'float',
        'exam_id'=>'integer','campus_id'=>'integer','grade_number'=>'integer','room_number'=>'integer','room_seat'=>'integer','grade_exam_number'=>'integer','exam_center_id'=>'integer','seat_scope'=>'integer'];
    public function examCenter() { return $this->belongsTo(ScholarshipExamCenter::class,'exam_center_id'); }
    public function student() { return $this->belongsTo(Student::class); }
    public function enrollment() { return $this->belongsTo(StudentEnrollment::class,'student_enrollment_id'); }
    public function exam() { return $this->belongsTo(ScholarshipExam::class, 'exam_id'); }
    public function scores() { return $this->hasMany(ScholarshipScore::class, 'candidate_id'); }
}
