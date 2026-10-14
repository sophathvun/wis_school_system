<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkippingDocumentArchive extends Model
{
    protected $guarded=['id'];
    protected $casts=['confirmed_at'=>'datetime'];
    public function parts() { return $this->hasMany(SkippingDocumentArchivePart::class,'archive_id')->orderBy('number'); }
    public function files() { return $this->hasMany(SkippingDocumentArchiveFile::class,'archive_id'); }
    public function creator() { return $this->belongsTo(User::class,'created_by'); }
    public function campus() { return $this->belongsTo(SchoolInfo::class); }
}
