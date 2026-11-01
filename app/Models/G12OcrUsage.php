<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class G12OcrUsage extends Model {
    protected $table='tb_g12_ocr_usage';protected $guarded=['id'];
    protected $casts=['image_index'=>'integer','import_id'=>'integer','user_id'=>'integer'];
}
