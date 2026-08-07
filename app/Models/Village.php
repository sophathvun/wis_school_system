<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Village extends Model
{
    protected $table = 'tb_village';
    protected $fillable = ['commune_id','village_code','village_name_en','village_name_kh','status'];
    protected $casts = ['status'=>'boolean'];

    public function commune()
    {
        return $this->belongsTo(Commune::class);
    }

    public function setVillageNameEnAttribute($value): void
    {
        $this->attributes['village_name_en'] = is_null($value) ? null : str_replace('"', '', (string) $value);
    }

    public function getVillageNameEnAttribute($value): ?string
    {
        return is_null($value) ? null : str_replace('"', '', (string) $value);
    }

    public function setVillageNameKhAttribute($value): void
    {
        $this->attributes['village_name_kh'] = is_null($value) ? null : str_replace('"', '', (string) $value);
    }

    public function getVillageNameKhAttribute($value): ?string
    {
        return is_null($value) ? null : str_replace('"', '', (string) $value);
    }
}
