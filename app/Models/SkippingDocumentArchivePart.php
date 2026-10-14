<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkippingDocumentArchivePart extends Model
{
    protected $guarded=['id'];
    protected $casts=['verified_at'=>'datetime','downloaded_at'=>'datetime','restored_at'=>'datetime'];
    public function archive() { return $this->belongsTo(SkippingDocumentArchive::class,'archive_id'); }
    public function files() { return $this->hasMany(SkippingDocumentArchiveFile::class,'part_id'); }
}
