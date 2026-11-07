<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class G9ExamCertificateTracking extends Model
{
    protected $table='tb_g9_exam_certificate_tracking';
    protected $guarded=['id'];
    protected $casts=['version'=>'integer','import_source'=>'array'];
    public function candidate(){return $this->belongsTo(G9ExamCandidate::class,'candidate_id');}
}
