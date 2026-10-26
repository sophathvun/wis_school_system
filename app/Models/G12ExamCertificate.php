<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class G12ExamCertificate extends Model {protected $table='tb_g12_exam_certificate';protected $guarded=['id'];protected $casts=['managed'=>'boolean','version'=>'integer'];}
