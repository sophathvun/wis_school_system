<?php

namespace App\Support;

use App\Models\Commune;
use App\Models\Country;
use App\Models\District;
use App\Models\Province;
use App\Models\Staff;
use App\Models\Village;
use Illuminate\Validation\ValidationException;

class StaffAddress
{
    public const LEVELS = [
        'country' => [Country::class, null, 'Country'],
        'province' => [Province::class, 'country_id', 'Province/City'],
        'district' => [District::class, 'province_id', 'District/Khan'],
        'commune' => [Commune::class, 'district_id', 'Commune/Sangkat'],
        'village' => [Village::class, 'commune_id', 'Village'],
    ];

    public static function options(string $level, ?int $parentId = null, ?int $selectedId = null)
    {
        [$model, $parent] = self::LEVELS[$level];
        $columns = ['id', $level.'_name_en', $level.'_name_kh'];
        if ($parent) $columns[] = $parent;
        if ($level === 'country') $columns[] = 'flag_path';
        return $model::query()
            ->when($parent, fn($query) => $parentId ? $query->where($parent, $parentId) : $query->whereRaw('1=0'))
            ->where(fn($query) => $query->where('status',1)->when($selectedId, fn($query) => $query->orWhere('id',$selectedId)))
            ->orderBy($level.'_name_en')->get($columns);
    }

    public static function formData(?Staff $staff): array
    {
        $data = [];
        $parentId = null;
        foreach (self::LEVELS as $level => [$model, $parent, $label]) {
            $id = old('address_'.$level.'_id', $staff?->{'address_'.$level.'_id'});
            $english = $level === 'country' ? '' : old($level.'_en', $staff?->{$level.'_en'});
            $khmer = $level === 'country' ? '' : old($level.'_kh', $staff?->{$level.'_kh'});
            // Match older text-only addresses only when the location is unambiguous.
            if (!$id && $english) {
                $matches = $model::where($level.'_name_en',$english)
                    ->when($parent, fn($query) => $query->where($parent,$parentId))
                    ->when($khmer, fn($query) => $query->where($level.'_name_kh',$khmer))->limit(2)->get();
                if ($matches->count() === 1) $id = $matches->first()->id;
            }
            if ($level === 'country' && !$id && !session()->hasOldInput('address_country_id')) {
                $province = $staff?->address_province_id ? Province::find($staff->address_province_id) : null;
                if (!$province && $staff?->province_en) {
                    $matches = Province::where('province_name_en',$staff->province_en)->limit(2)->get();
                    if ($matches->count() === 1) $province = $matches->first();
                }
                $id = $province?->country_id ?? Country::where('status',1)->where(fn($query) => $query->where('country_code','kh')->orWhere('country_name_en','Cambodia'))->value('id');
            }
            $options = self::options($level, $parentId ? (int)$parentId : null, $id ? (int)$id : null);
            if ($id && !$options->contains('id',(int)$id)) $id = null;
            $data[$level] = compact('label','id','english','khmer','options');
            $parentId = $id;
        }
        return $data;
    }

    public static function normalize(array $data): array
    {
        $parentId = null;
        foreach (self::LEVELS as $level => [$model, $parent, $label]) {
            $field = 'address_'.$level.'_id';
            $id = $data[$field] ?? null;
            if ($id) {
                $location = $model::findOrFail($id);
                if ($parent && (!$parentId || (int)$location->{$parent} !== (int)$parentId)) {
                    throw ValidationException::withMessages([$field => 'Select a '.$label.' belonging to the selected parent location.']);
                }
                if ($level !== 'country') {
                    $data[$level.'_en'] = $location->{$level.'_name_en'};
                    $data[$level.'_kh'] = $location->{$level.'_name_kh'};
                }
            }
            $parentId = $id;
        }
        return $data;
    }
}
