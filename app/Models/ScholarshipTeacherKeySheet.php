<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScholarshipTeacherKeySheet extends Model
{
    protected $table='tb_scholarship_teacher_key_sheet';
    protected $guarded=['id'];
    protected $casts=['paper_id'=>'integer','snapshot'=>'array'];
    public function paper(){return $this->belongsTo(ScholarshipPaper::class,'paper_id');}
}
