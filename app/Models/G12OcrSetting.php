<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class G12OcrSetting extends Model {
    protected $table='tb_g12_ocr_setting';protected $guarded=['id'];
    protected $casts=['enabled'=>'boolean','monthly_limit'=>'integer'];
}
