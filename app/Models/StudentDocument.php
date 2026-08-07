<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentDocument extends Model
{
    use SoftDeletes;

    protected $table = 'tb_student_document';
    protected $fillable = ['student_id', 'document_type_id', 'document_type', 'title', 'document_number', 'issue_date', 'expiry_date', 'description', 'file_id', 'file_path', 'original_filename', 'mime_type', 'file_size', 'status', 'uploaded_by'];
    protected function casts(): array { return ['issue_date' => 'date:Y-m-d', 'expiry_date' => 'date:Y-m-d', 'status' => 'integer']; }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function type(): BelongsTo { return $this->belongsTo(StudentDocumentType::class, 'document_type_id'); }
    public function uploadedBy(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
}
