<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ScholarshipAnswerScan extends Model
{
    protected $table='tb_scholarship_answer_scan';
    protected $guarded=['id'];
    protected $casts=['sheet_id'=>'integer','key_id'=>'integer','source_scan_id'=>'integer','detected'=>'array','responses'=>'array','flags'=>'array','confidence'=>'array','score'=>'float','candidate_version'=>'integer','verified_at'=>'datetime'];
    public function sheet(){return $this->belongsTo(ScholarshipAnswerSheet::class,'sheet_id');}
    public function key(){return $this->belongsTo(ScholarshipAnswerKey::class,'key_id');}
}
