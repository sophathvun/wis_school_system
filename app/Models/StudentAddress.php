<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentAddress extends Model
{
    use SoftDeletes;

    protected $table = 'tb_student_address';
    protected $fillable = ['student_id', 'address_type', 'line_1', 'line_2', 'city', 'province', 'country', 'postal_code', 'is_primary'];
    protected function casts(): array { return ['is_primary' => 'boolean']; }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
}
