<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ScholarshipInvigilatorDuty extends Model
{
    protected $table='tb_scholarship_invigilator_duty';
    protected $guarded=['id'];
    protected $casts=['exam_id'=>'integer','invigilator_id'=>'integer','session_id'=>'integer','exam_center_id'=>'integer','room_number'=>'integer'];
    public function invigilator(){return $this->belongsTo(ScholarshipInvigilator::class,'invigilator_id');}
    public function session(){return $this->belongsTo(ScholarshipExamSession::class,'session_id');}
    public function examCenter(){return $this->belongsTo(ScholarshipExamCenter::class,'exam_center_id');}
}
