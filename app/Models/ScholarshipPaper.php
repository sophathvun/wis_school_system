<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Grade-specific paper; linked setup is protected until a permitted pre-scan unlink. */
class ScholarshipPaper extends Model
{
    protected $table = 'tb_scholarship_paper';
    protected $guarded = ['id'];
    protected $fillable = ['subject_id','grade_number','paper_version','question_count','answer_choices','points_per_question','draft_answers','draft_points','version'];
    protected $casts = ['subject_id'=>'integer','grade_number'=>'integer','question_count'=>'integer','answer_choices'=>'array','points_per_question'=>'float','draft_answers'=>'array','draft_points'=>'array','version'=>'integer'];

    public function subject() { return $this->belongsTo(ScholarshipSubject::class, 'subject_id'); }
    public function keys() { return $this->hasMany(ScholarshipAnswerKey::class,'paper_id'); }
    public function sheets() { return $this->hasMany(ScholarshipAnswerSheet::class,'paper_id'); }
    public function scores() { return $this->hasMany(ScholarshipScore::class,'source_paper_id'); }
    public function latestKey() { return $this->hasOne(ScholarshipAnswerKey::class,'paper_id')->latestOfMany(); }
}
