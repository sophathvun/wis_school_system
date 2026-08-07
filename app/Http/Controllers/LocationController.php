<?php

namespace App\Http\Controllers;

use App\Models\Commune;
use App\Models\Country;
use App\Models\District;
use App\Models\Province;
use App\Models\Village;
use Illuminate\Http\Request;
use App\Support\SettingsQuery;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class LocationController
{
    private array $levels = [
        'country' => [Country::class, 'country_name_en', 'country_name_kh', null],
        'province' => [Province::class, 'province_name_en', 'province_name_kh', 'country_id'],
        'district' => [District::class, 'district_name_en', 'district_name_kh', 'province_id'],
        'commune' => [Commune::class, 'commune_name_en', 'commune_name_kh', 'district_id'],
        'village' => [Village::class, 'village_name_en', 'village_name_kh', 'commune_id'],
    ];

    public function index() { return view('locations'); }

    private function whereLocationName($query, string $en, string $kh, string $search): void
    {
        $query->where($en, 'like', "%{$search}%")
            ->orWhere($kh, 'like', "%{$search}%");
    }

    private function applySearch($query, string $level, string $en, ?string $kh, string $search): void
    {
        $query->where(function ($sub) use ($level, $en, $kh, $search) {
            $sub->where($en, 'like', "%{$search}%");
            if ($kh) {
                $sub->orWhere($kh, 'like', "%{$search}%");
            }

            if ($level === 'province') {
                $sub->orWhereHas('country', fn($country) => $this->whereLocationName($country, 'country_name_en', 'country_name_kh', $search));
            }

            if ($level === 'district') {
                $sub->orWhereHas('province', function ($province) use ($search) {
                    $this->whereLocationName($province, 'province_name_en', 'province_name_kh', $search);
                    $province->orWhereHas('country', fn($country) => $this->whereLocationName($country, 'country_name_en', 'country_name_kh', $search));
                });
            }

            if ($level === 'commune') {
                $sub->orWhereHas('district', function ($district) use ($search) {
                    $this->whereLocationName($district, 'district_name_en', 'district_name_kh', $search);
                    $district->orWhereHas('province', function ($province) use ($search) {
                        $this->whereLocationName($province, 'province_name_en', 'province_name_kh', $search);
                        $province->orWhereHas('country', fn($country) => $this->whereLocationName($country, 'country_name_en', 'country_name_kh', $search));
                    });
                });
            }

            if ($level === 'village') {
                $sub->orWhereHas('commune', function ($commune) use ($search) {
                    $this->whereLocationName($commune, 'commune_name_en', 'commune_name_kh', $search);
                    $commune->orWhereHas('district', function ($district) use ($search) {
                        $this->whereLocationName($district, 'district_name_en', 'district_name_kh', $search);
                        $district->orWhereHas('province', function ($province) use ($search) {
                            $this->whereLocationName($province, 'province_name_en', 'province_name_kh', $search);
                            $province->orWhereHas('country', fn($country) => $this->whereLocationName($country, 'country_name_en', 'country_name_kh', $search));
                        });
                    });
                });
            }
        });
    }

    public function options(Request $request)
    {
        $map = [
            'countries' => Country::where('status', 1)->orderBy('country_name_en')->get(['id','country_name_en','country_name_kh','nationality_name_en','nationality_name_kh','flag_path']),
            'provinces' => Province::where('status', 1)->when($request->country_id, fn($q, $id) => $q->where('country_id', $id))->orderBy('province_name_en')->get(['id','country_id','province_name_en','province_name_kh']),
            'districts' => District::where('status', 1)->when($request->province_id, fn($q, $id) => $q->where('province_id', $id))->orderBy('district_name_en')->get(['id','province_id','district_name_en','district_name_kh']),
            'communes' => Commune::where('status', 1)->when($request->district_id, fn($q, $id) => $q->where('district_id', $id))->orderBy('commune_name_en')->get(['id','district_id','commune_name_en','commune_name_kh']),
            'villages' => Village::where('status', 1)->when($request->commune_id, fn($q, $id) => $q->where('commune_id', $id))->orderBy('village_name_en')->get(['id','commune_id','village_name_en','village_name_kh']),
        ];
        return response()->json($map);
    }

    public function fetch(Request $request)
    {
        abort_unless(isset($this->levels[$request->level]), 404);
        [$model, $en, $kh, $parent] = $this->levels[$request->level];
        $query = $model::query();
        if ($request->filled('search')) {
            $this->applySearch($query, $request->level, $en, $kh, $request->search);
        }
        if ($parent && $request->parent_id) $query->where($parent, $request->parent_id);
        $sortBy = $request->get('sortBy', 'name');
        $sortDir = $request->get('sortDir', 'asc') === 'desc' ? 'desc' : 'asc';
        if ($request->level === 'country') {
            $query->orderBy('country_name_en', $sortDir);
        } else {
            $relation = match ($parent) { 'country_id' => ['country', 'tb_country', 'country_name_en'], 'province_id' => ['province', 'tb_province', 'province_name_en'], 'district_id' => ['district', 'tb_district', 'district_name_en'], default => ['commune', 'tb_commune', 'commune_name_en'] };
            $query->with(match ($request->level) { 'district' => 'province.country', 'commune' => 'district.province.country', 'village' => 'commune.district.province.country', default => $relation[0] });
            if ($request->level === 'village') {
                $table = $model::make()->getTable();
                $needsLocationJoin = $request->filled('country_id')
                    || $request->filled('province_id')
                    || $request->filled('district_id')
                    || $request->filled('commune_id')
                    || in_array($sortBy, ['parent', 'district', 'province', 'country'], true);
                if ($needsLocationJoin) {
                    $query->join('tb_commune', "{$table}.commune_id", '=', 'tb_commune.id')
                        ->join('tb_district', 'tb_commune.district_id', '=', 'tb_district.id')
                        ->join('tb_province', 'tb_district.province_id', '=', 'tb_province.id')
                        ->join('tb_country', 'tb_province.country_id', '=', 'tb_country.id')
                        ->select("{$table}.*");
                    if ($request->filled('commune_id')) $query->where('tb_commune.id', $request->integer('commune_id'));
                    if ($request->filled('district_id')) $query->where('tb_district.id', $request->integer('district_id'));
                    if ($request->filled('province_id')) $query->where('tb_province.id', $request->integer('province_id'));
                    if ($request->filled('country_id')) $query->where('tb_country.id', $request->integer('country_id'));
                }
                if ($sortBy === 'parent') {
                    $query->orderBy('tb_commune.commune_name_en', $sortDir);
                } elseif ($sortBy === 'district') {
                    $query->orderBy('tb_district.district_name_en', $sortDir);
                } elseif ($sortBy === 'province') {
                    $query->orderBy('tb_province.province_name_en', $sortDir);
                } elseif ($sortBy === 'country') {
                    $query->orderBy('tb_country.country_name_en', $sortDir);
                } else {
                    $query->orderBy($en, $sortDir);
                }
            } elseif ($sortBy === 'parent') {
                $table = $model::make()->getTable();
                $query->join($relation[1], "{$table}.{$parent}", '=', "{$relation[1]}.id")->select("{$table}.*")->orderBy($relation[1] . '.' . $relation[2], $sortDir);
            } elseif ($sortBy === 'country' && in_array($request->level, ['district', 'commune'], true)) {
                $table = $model::make()->getTable();
                if ($request->level === 'district') {
                    $query->join('tb_province', "{$table}.province_id", '=', 'tb_province.id')->join('tb_country', 'tb_province.country_id', '=', 'tb_country.id');
                } else {
                    $query->join('tb_district', "{$table}.district_id", '=', 'tb_district.id')->join('tb_province', 'tb_district.province_id', '=', 'tb_province.id')->join('tb_country', 'tb_province.country_id', '=', 'tb_country.id');
                }
                $query->select("{$table}.*")->orderBy('tb_country.country_name_en', $sortDir);
            } else {
                $query->orderBy($en, $sortDir);
            }
        }
        return response()->json($query->paginate(SettingsQuery::perPage($request)));
    }

    public function save(Request $request)
    {
        abort_unless(isset($this->levels[$request->level]), 404);
        [$model, $en, $kh, $parent] = $this->levels[$request->level];
        $rules = ['name_en' => ['required','string','max:100'], 'name_kh' => ['nullable','string','max:100'], 'status' => ['required','boolean']];
        if ($request->level === 'country') {
            $rules['nationality_name_en'] = ['nullable', 'string', 'max:100'];
            $rules['nationality_name_kh'] = ['nullable', 'string', 'max:100'];
        }
        if ($parent) {
            $parentTable = match ($parent) { 'country_id' => 'tb_country', 'province_id' => 'tb_province', 'district_id' => 'tb_district', default => 'tb_commune' };
            $rules['parent_id'] = ['required', 'integer', "exists:{$parentTable},id"];
        }
        if ($request->level === 'country') $rules['country_code'] = ['nullable','string','max:10'];
        if ($request->level === 'country') $rules['flag_image'] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'];
        $data = $request->validate($rules);
        $item = $request->id ? $model::findOrFail($request->id) : new $model();
        $item->{$en} = $data['name_en']; $item->{$kh} = $data['name_kh'] ?? null; $item->status = $data['status'];
        if ($request->level === 'country') {
            $item->nationality_name_en = $data['nationality_name_en'] ?? null;
            $item->nationality_name_kh = $data['nationality_name_kh'] ?? null;
        }
        if ($parent) $item->{$parent} = $data['parent_id'];
        if ($request->level === 'country') {
            $item->country_code = $data['country_code'] ?? null;
            if ($request->hasFile('flag_image')) {
                if ($item->flag_path && str_starts_with($item->flag_path, 'storage/')) {
                    Storage::disk('public')->delete(substr($item->flag_path, 8));
                }
                $item->flag_path = 'storage/' . $request->file('flag_image')->store('country_flags', 'public');
            } elseif ($request->filled('flag_path')) {
                $item->flag_path = $request->flag_path;
            }
        }
        $item->save();
        return response()->json(['status'=>'success','message'=>'Location saved successfully.','data'=>$item]);
    }

    public function delete(Request $request, $id)
    {
        abort_unless(isset($this->levels[$request->level]), 404);
        $model = $this->levels[$request->level][0]; $model::findOrFail($id)->delete();
        return response()->json(['status'=>'success','message'=>'Location deleted successfully.']);
    }
}
