<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GepStudent extends Model
{
    protected $table = 'tb_gep_student';
    protected $fillable = ['student_id', 'source', 'photo_path','profile','student_identifier'];
    protected $casts = ['profile'=>'array'];
    protected $appends = ['gep_code', 'photo_url'];
    protected static function booted(): void
    {
        static::saving(function(self $student){
            if($student->exists && $student->isDirty('student_identifier') && filled($student->getRawOriginal('student_identifier')))
                throw \Illuminate\Validation\ValidationException::withMessages(['student_identifier'=>'A student keeps their existing GEP Student ID.']);
            if($student->isDirty('student_identifier'))app(\App\Services\GepStudentIdAllocator::class)->observeIdentifier($student->student_identifier);
            if(filled($student->student_identifier) && blank($student->profile['family_number']??null)){
                $student->profile=array_replace($student->profile??[],['family_number'=>'GEP-F'.$student->student_identifier]);
            }
        });
        static::saved(function(self $student){
            $number=trim($student->profile['family_number']??'');
            if($number!=='')GepFamily::firstOrCreate(['family_number'=>$number],['parent_information'=>[]]);
        });
    }
    public function getPhotoUrlAttribute(): ?string
    {
        $path = $this->photo_path;
        return $path ? \Illuminate\Support\Facades\Storage::disk('public')->url($path) : null;
    }
    public function student(){return $this->belongsTo(Student::class);}
    public function enrollments(){return $this->hasMany(GepEnrollment::class);}
    public function familyInformation(bool $lock=false): array
    {
        $query=GepFamily::where('family_number',$this->profile['family_number']??'');
        $family=($lock?$query->lockForUpdate():$query)->first();
        if($family)return $family->parent_information??[];
        if(array_key_exists('parent_information',$this->profile??[]))return $this->profile['parent_information'];
        $parents=$this->enrollments()->whereNotNull('parent_information')->latest('id')->value('parent_information');
        if($parents!==null)return $parents;
        $enrollment=$this->enrollments()->whereNotNull('guardian_name')->latest('id')->first();
        return $enrollment?['guardian'=>['name_en'=>$enrollment->guardian_name,'phone'=>$enrollment->guardian_phone]]:[];
    }
    public function saveFamilyInformation(array $parents,User $user): void
    {
        $profile=$this->profile??[];
        $before=$this->familyInformation();
        $number=trim($profile['family_number']??'');
        if($number==='')throw \Illuminate\Validation\ValidationException::withMessages(['family_number'=>'A GEP Family Number is required.']);
        $family=GepFamily::firstOrCreate(['family_number'=>$number],['parent_information'=>[]]);
        $family=GepFamily::whereKey($family->id)->lockForUpdate()->firstOrFail();
        if($before!==$parents){
            foreach(GepEnrollment::withTrashed()->whereIn('gep_student_id',self::where('profile->family_number',$number)->select('id'))->pluck('campus_id')->unique() as $campus)
                abort_unless(\App\Support\GepEnrollmentPermissions::allows($user,'edit',(int)$campus),403,'You cannot update this family outside your permitted campuses.');
            $change=['before'=>$family->parent_information??[],'after'=>$parents,'changed_by'=>$user->id,'changed_at'=>now()->toIso8601String()];
            $family->update(['parent_information'=>$parents,'history'=>[...($family->history??[]),$change]]);
            $profile['family_history'][]=$change;
        }
        $profile['parent_information']=$parents;
        $this->update(['profile'=>$profile]);
    }
    public function getGepCodeAttribute(): string {return 'GEP-'.str_pad((string)$this->id,6,'0',STR_PAD_LEFT);}
}
