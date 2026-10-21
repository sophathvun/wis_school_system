<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ScholarshipCertificateSetting extends Model
{
    protected $table='tb_scholarship_certificate_setting';
    protected $guarded=['id'];
    protected $casts=['issue_date'=>'date','signers'=>'array','layouts'=>'array','version'=>'integer'];
}
