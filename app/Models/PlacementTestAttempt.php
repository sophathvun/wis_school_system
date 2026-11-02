<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlacementTestAttempt extends Model
{
    protected $table = 'tb_placement_test_attempt';

    protected $guarded = ['id'];

    protected $casts = ['subject_scores' => 'array', 'snapshot' => 'array', 'started_at' => 'datetime', 'expires_at' => 'datetime', 'submitted_at' => 'datetime', 'verified_at' => 'datetime', 'score' => 'decimal:2', 'percentage' => 'decimal:2', 'max_score' => 'decimal:2'];

    public function applicant()
    {
        return $this->belongsTo(PlacementTestApplicant::class, 'applicant_id');
    }

    public function form()
    {
        return $this->belongsTo(PlacementTestForm::class, 'form_id');
    }

    public function answers()
    {
        return $this->hasMany(PlacementTestAnswer::class, 'attempt_id')->orderBy('question_order');
    }

    public function scans()
    {
        return $this->hasMany(PlacementTestScan::class, 'attempt_id');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
