<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScholarshipEvent extends Model
{
    protected $table = 'tb_scholarship_event';
    public const UPDATED_AT = null;
    protected $guarded = ['id'];
    protected $casts = ['details'=>'array'];
    public function user() { return $this->belongsTo(User::class); }
}
