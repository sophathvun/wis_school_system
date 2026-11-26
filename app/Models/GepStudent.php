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
        });
    }
    public function getPhotoUrlAttribute(): ?string
    {
        $path = $this->photo_path;
        return $path ? \Illuminate\Support\Facades\Storage::disk('public')->url($path) : null;
    }
    public function student(){return $this->belongsTo(Student::class);}
    public function enrollments(){return $this->hasMany(GepEnrollment::class);}
    public function getGepCodeAttribute(): string {return 'GEP-'.str_pad((string)$this->id,6,'0',STR_PAD_LEFT);}
}
