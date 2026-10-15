<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScholarshipCommittee extends Model
{
    protected $table = 'tb_scholarship_committee';
    protected $guarded = ['id'];
    protected $casts = ['exam_id'=>'integer','user_id'=>'integer','grade_number'=>'integer','room_number'=>'integer','exam_center_id'=>'integer'];
    public function examCenter() { return $this->belongsTo(ScholarshipExamCenter::class,'exam_center_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
