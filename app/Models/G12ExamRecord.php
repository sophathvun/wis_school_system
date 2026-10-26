<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class G12ExamRecord extends Model {
    protected $table='tb_g12_exam_record';protected $guarded=['id'];
    protected $casts=['data'=>'array','version'=>'integer','campus_id'=>'integer','center_id'=>'integer'];
    public function center(){return $this->belongsTo(G12ExamCenter::class,'center_id');}
    public function campus(){return $this->belongsTo(SchoolInfo::class,'campus_id');}
    public function certificates(){return $this->hasMany(G12ExamCertificate::class,'record_id');}
    public function files(){return $this->hasMany(G12ExamFile::class,'record_id');}
}
