<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ScholarshipExamSession extends Model
{
    protected $table='tb_scholarship_exam_session';
    protected $guarded=['id'];
    protected $casts=['exam_id'=>'integer','number'=>'integer','exam_date'=>'date'];
}
