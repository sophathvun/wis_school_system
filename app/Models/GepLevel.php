<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GepLevel extends Model
{
    protected $table='tb_gep_level';
    protected $fillable=['type','level_name','short_name','program','sort_order','status'];
    protected $casts=['status'=>'boolean','sort_order'=>'integer'];
}
