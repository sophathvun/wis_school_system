<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GepClass extends Model
{
    protected $table='tb_gep_class';
    protected $fillable=['class_name','sort_order','status'];
    protected $hidden=['short_name'];
    protected $casts=['status'=>'boolean','sort_order'=>'integer'];
}
