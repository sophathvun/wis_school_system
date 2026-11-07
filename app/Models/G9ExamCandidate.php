<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class G9ExamCandidate extends Model
{
    protected $table='tb_g9_exam_candidate';
    protected $guarded=['id'];
    protected $casts=['data'=>'array','legacy_data'=>'array','version'=>'integer'];
    public function academicYear(){return $this->belongsTo(AcademicYear::class);}
    public function campus(){return $this->belongsTo(SchoolInfo::class);}
    public function certificateTracking(){return $this->hasOne(G9ExamCertificateTracking::class,'candidate_id');}
    public function certificateFiles(){return $this->hasMany(G9ExamCertificateFile::class,'candidate_id');}
    public function certificateNumber(){return $this->hasOne(G9ExamCertificateNumber::class,'candidate_id');}
}
