<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ScholarshipAnswerScan extends Model
{
    protected $table='tb_scholarship_answer_scan';
    protected $guarded=['id'];
    protected $casts=['sheet_id'=>'integer','key_id'=>'integer','source_scan_id'=>'integer','detected'=>'array','responses'=>'array','flags'=>'array','confidence'=>'array','absence'=>'array','score'=>'float','candidate_version'=>'integer','verified_at'=>'datetime'];
    public function detectedAbsent(): bool {return ($this->absence['state']??null)==='absent';}
    public function absenceNeedsReview(): bool {return in_array($this->absence['state']??null,['unclear','conflict'],true);}
    public function subjectAbsent(): bool {return $this->status==='accepted'?(bool)($this->absence['verified_absent']??false):$this->detectedAbsent();}
    public function flaggedCount(): int {return $this->subjectAbsent()?0:count($this->flags)+(int)($this->status==='pending'&&$this->absenceNeedsReview());}
    public function sheet(){return $this->belongsTo(ScholarshipAnswerSheet::class,'sheet_id');}
    public function key(){return $this->belongsTo(ScholarshipAnswerKey::class,'key_id');}
}
