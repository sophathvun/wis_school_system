<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GepClassType extends Model
{
    protected $table='tb_gep_class_type';
    protected $fillable=['type_name','sort_order','status'];
    protected $casts=['status'=>'boolean','sort_order'=>'integer'];
}
