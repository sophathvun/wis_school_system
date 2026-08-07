<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StudentDocumentType extends Model { protected $table = 'tb_student_document_type'; protected $fillable = ['type_key','name_en','name_kh','sort_order','status']; protected $casts = ['status'=>'boolean']; public function documents(){ return $this->hasMany(StudentDocument::class,'document_type_id'); } }
