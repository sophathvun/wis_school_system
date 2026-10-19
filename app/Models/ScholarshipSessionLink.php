<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ScholarshipSessionLink extends Model
{
    protected $table='tb_scholarship_session_link';
    protected $guarded=['id'];
    protected $casts=['exam_id'=>'integer','exam_center_id'=>'integer','session_ids'=>'array'];
    public function examCenter(){return $this->belongsTo(ScholarshipExamCenter::class,'exam_center_id');}
}
