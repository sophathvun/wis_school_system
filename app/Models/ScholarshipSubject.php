<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScholarshipSubject extends Model
{
    protected $table = 'tb_scholarship_subject';
    protected $guarded = ['id'];
    protected $casts = ['max_score'=>'float','weight'=>'float'];
    public function exam() { return $this->belongsTo(ScholarshipExam::class, 'exam_id'); }
    public function papers() { return $this->hasMany(ScholarshipPaper::class, 'subject_id'); }
}
