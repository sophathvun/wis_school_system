<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GepFamily extends Model
{
    protected $table='tb_gep_family';
    protected $fillable=['family_number','parent_information','history'];
    protected $casts=['parent_information'=>'array','history'=>'array'];
}
