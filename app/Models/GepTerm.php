<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GepTerm extends Model
{
    protected $table='tb_gep_term';
    protected $fillable=['term_name','short_name','status'];
    protected $casts=['status'=>'boolean'];
}
