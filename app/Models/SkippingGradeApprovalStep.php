<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkippingGradeApprovalStep extends Model
{
    protected $table = 'tb_skipping_grade_approval_steps';
    protected $guarded = ['id'];
    protected $casts = ['is_final'=>'boolean','reviewer_snapshot'=>'array','actor_snapshot'=>'array','acted_at'=>'datetime'];
    public function request() { return $this->belongsTo(StudentSkippingGrade::class, 'request_id'); }
    public function reassignments() { return $this->hasMany(SkippingGradeReassignment::class, 'step_id')->orderBy('id'); }
    public function reviewers() { return $this->hasMany(SkippingGradeStepReviewer::class, 'step_id')->orderBy('id'); }

    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where(fn($q) => $q->whereHas('reviewers', fn($members) => $members->where('user_id', $userId))
            ->orWhere(fn($old) => $old->doesntHave('reviewers')->where('reviewer_id', $userId)));
    }

    public function reviewerPeople(): array
    {
        return $this->reviewers->isNotEmpty() ? $this->reviewers->pluck('reviewer_snapshot')->all() : [$this->reviewer_snapshot];
    }

    public function reviewerNames(): string
    {
        return implode(', ', array_column($this->reviewerPeople(), 'name'));
    }

    public function trackingEntries(): array
    {
        $individual=$this->scope==='central';
        $people=$individual?$this->reviewerPeople():[$this->actor_snapshot??$this->reviewer_snapshot];
        $count=count($this->reviewerPeople());
        return array_map(function ($person) use ($individual,$count) {
            $acted=$this->acted_by!==null && (!$individual || (int)($person['user_id']??$this->reviewer_id)===(int)$this->acted_by);
            $alternate=$individual && $this->acted_by!==null && !$acted;
            if ($acted && $this->actor_snapshot) $person=$this->actor_snapshot;
            return [
                'person'=>$person,
                'name'=>$individual?($person['name']??'Reviewer'):($this->actor_snapshot['name']??$this->reviewerNames()),
                'status'=>$alternate?'cancelled':$this->status,
                'label'=>$alternate?($this->status==='approved'?'Step completed':'Step closed'):($this->status==='pending'?'Awaiting Approval':ucfirst($this->status)),
                'acted_at'=>$acted || !$individual?$this->acted_at:null,
                'comments'=>$acted || !$individual?$this->comments:null,
                'individual'=>$individual,'reviewer_count'=>$count,
            ];
        },$people);
    }
}
