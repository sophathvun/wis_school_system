<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ScholarshipAnswerSheet extends Model
{
    protected $table='tb_scholarship_answer_sheet';
    protected $guarded=['id'];
    protected $casts=['paper_id'=>'integer','candidate_id'=>'integer','snapshot'=>'array'];
    public function paper(){return $this->belongsTo(ScholarshipPaper::class,'paper_id');}
    public function candidate(){return $this->belongsTo(ScholarshipCandidate::class,'candidate_id');}
    public function scans(){return $this->hasMany(ScholarshipAnswerScan::class,'sheet_id');}
}
