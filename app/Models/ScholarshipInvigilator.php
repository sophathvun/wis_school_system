<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ScholarshipInvigilator extends Model
{
    protected $table='tb_scholarship_invigilator';
    protected $guarded=['id'];
    protected $casts=['exam_id'=>'integer'];
    public function duties(){return $this->hasMany(ScholarshipInvigilatorDuty::class,'invigilator_id');}
}
