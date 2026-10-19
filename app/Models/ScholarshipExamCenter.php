<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScholarshipExamCenter extends Model
{
    protected $table='tb_scholarship_exam_center';
    protected $guarded=['id'];
    protected $casts=['exam_id'=>'integer','position'=>'integer'];
    public function exam(){return $this->belongsTo(ScholarshipExam::class,'exam_id');}
    public function campuses(){return $this->belongsToMany(SchoolInfo::class,'tb_scholarship_center_campus','exam_center_id','campus_id');}
    public function reportDate(\Carbon\CarbonInterface $date): string
    {
        return ($this->location?:'Phnom Penh').', '.$date->format('F j, Y');
    }
}
