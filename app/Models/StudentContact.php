<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentContact extends Model
{
    use SoftDeletes;

    protected $table = 'tb_student_contact';
    protected $fillable = ['student_id', 'contact_type', 'contact_value', 'is_primary', 'status'];
    protected function casts(): array { return ['is_primary' => 'boolean', 'status' => 'integer']; }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
}
