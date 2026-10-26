<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class G12ExamCenter extends Model {
    protected $table='tb_g12_exam_center';protected $guarded=['id'];protected $casts=['active'=>'boolean'];
    protected static function booted(): void {
        static::saved(function(self $center){
            if(trim((string)$center->source_id)==='')$center->updateQuietly(['source_id'=>'EC-'.str_pad((string)$center->id,6,'0',STR_PAD_LEFT)]);
        });
    }
}
