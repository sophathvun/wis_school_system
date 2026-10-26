<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class G12ExamImport extends Model {protected $table='tb_g12_exam_import';protected $guarded=['id'];protected $attributes=['status'=>'preview'];protected $casts=['user_id'=>'integer','payload'=>'array','summary'=>'array','expires_at'=>'datetime','confirmed_at'=>'datetime'];}
