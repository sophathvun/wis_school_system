<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ScholarshipCertificate extends Model
{
    protected $table='tb_scholarship_certificate';
    protected $guarded=['id'];
    protected $casts=['snapshot'=>'array','academic_year_id'=>'integer','student_id'=>'integer','campus_id'=>'integer','sequence'=>'integer','cancelled_at'=>'datetime'];
}
