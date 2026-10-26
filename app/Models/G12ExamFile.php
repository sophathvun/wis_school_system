<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class G12ExamFile extends Model {protected $table='tb_g12_exam_file';protected $guarded=['id'];public function record(){return $this->belongsTo(G12ExamRecord::class,'record_id');}}
