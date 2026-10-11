<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkippingGradeStepReviewer extends Model
{
    protected $table = 'tb_skipping_grade_step_reviewers';
    protected $guarded = ['id'];
    protected $casts = ['reviewer_snapshot' => 'array', 'grade_ids' => 'array'];

    public function step() { return $this->belongsTo(SkippingGradeApprovalStep::class, 'step_id'); }
}
