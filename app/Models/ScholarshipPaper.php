<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Reserved configuration for the future multiple-choice answer-sheet module. */
class ScholarshipPaper extends Model
{
    protected $table = 'tb_scholarship_paper';
    protected $guarded = ['id'];
    protected $casts = ['subject_id'=>'integer','grade_number'=>'integer','question_count'=>'integer','answer_choices'=>'array','points_per_question'=>'float'];

    public function subject() { return $this->belongsTo(ScholarshipSubject::class, 'subject_id'); }
}
