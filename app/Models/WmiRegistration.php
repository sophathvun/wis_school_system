<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WmiRegistration extends Model
{
    protected $guarded=['id'];
    protected $casts=['date_of_birth'=>'date:Y-m-d'];
    public function year(){return $this->belongsTo(WmiYear::class,'wmi_year_id');}
    public function center(){return $this->belongsTo(WmiExamCenter::class,'wmi_exam_center_id');}
    public function school(){return $this->belongsTo(WmiSchool::class,'wmi_school_id');}
    public function enrollment(){return $this->belongsTo(StudentEnrollment::class,'enrollment_id');}
    public function registeredBy(){return $this->belongsTo(User::class,'registered_by');}
}
