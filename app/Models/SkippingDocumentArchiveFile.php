<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkippingDocumentArchiveFile extends Model
{
    protected $guarded=['id'];
    protected $casts=['student'=>'array','deleted_at'=>'datetime','restored_at'=>'datetime'];
}
