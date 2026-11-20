<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GepTermAssignment extends Model
{
    protected $table='tb_gep_term_assignment';
    protected $fillable=['academic_year_id','term_id','sort_order','start_date','end_date'];
    protected $casts=['sort_order'=>'integer','academic_year_id'=>'integer','term_id'=>'integer'];
    public function academicYear(){return $this->belongsTo(GepAcademicYear::class);}
    public function term(){return $this->belongsTo(GepTerm::class);}
}
