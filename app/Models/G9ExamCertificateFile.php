<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class G9ExamCertificateFile extends Model
{
    protected $table='tb_g9_exam_certificate_file';
    protected $guarded=['id'];
    protected $casts=['size'=>'integer'];
    public function candidate(){return $this->belongsTo(G9ExamCandidate::class,'candidate_id');}
}
