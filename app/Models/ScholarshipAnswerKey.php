<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ScholarshipAnswerKey extends Model
{
    protected $table='tb_scholarship_answer_key';
    protected $guarded=['id'];
    protected $fillable=['paper_id','revision','answers','question_points','approved_by','approved_at'];
    protected $casts=['paper_id'=>'integer','answers'=>'array','question_points'=>'array','approved_at'=>'datetime','revision'=>'integer'];
    public function paper(){return $this->belongsTo(ScholarshipPaper::class,'paper_id');}
    public function scans(){return $this->hasMany(ScholarshipAnswerScan::class,'key_id');}
}
