<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class G9ExamCertificateNumber extends Model
{
    protected $table='tb_g9_exam_certificate_number';
    protected $guarded=['id'];
    protected $casts=['sequence'=>'integer'];
}
