<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GepGroup extends Model
{
    protected $table='tb_gep_group';
    protected $fillable=['session_name','group_code','sort_order','status'];
    protected $casts=['status'=>'boolean','sort_order'=>'integer'];
}
