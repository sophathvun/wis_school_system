<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScholarshipScore extends Model
{
    protected $table = 'tb_scholarship_score';
    protected $guarded = ['id'];
    protected $casts = ['score'=>'float','source_paper_id'=>'integer'];
    public function sourcePaper() { return $this->belongsTo(ScholarshipPaper::class, 'source_paper_id'); }
}
