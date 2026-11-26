<?php
namespace App\Models;

use Illuminate\Database\Eloquent\{Model, SoftDeletes};

class GepEnrollment extends Model
{
    use SoftDeletes;
    protected $table = 'tb_gep_enrollment';
    protected $fillable = ['gep_student_id','term_assignment_id','campus_id','level_id','class_type_id','class_id','group_id','student_type','enrollment_status','enrolled_on','notes','guardian_name','guardian_phone','created_by','updated_by','active_slot'];
    protected $casts = ['enrolled_on'=>'date:Y-m-d', 'parent_information'=>'array'];
    protected static function booted(): void
    {
        static::saving(function(self $enrollment){
            if(blank($enrollment->student_type)){
                $previous=self::where('gep_student_id',$enrollment->gep_student_id);
                if($enrollment->exists)$previous->where('id','!=',$enrollment->id);
                $enrollment->student_type=$previous->exists()?'old':'new';
            }
        });
    }
    public function gepStudent(){return $this->belongsTo(GepStudent::class);}
    public function termAssignment(){return $this->belongsTo(GepTermAssignment::class);}
    public function campus(){return $this->belongsTo(SchoolInfo::class);}
    public function level(){return $this->belongsTo(GepLevel::class);}
    public function classType(){return $this->belongsTo(GepClassType::class);}
    public function schoolClass(){return $this->belongsTo(GepClass::class,'class_id');}
    public function group(){return $this->belongsTo(GepGroup::class);}
}
