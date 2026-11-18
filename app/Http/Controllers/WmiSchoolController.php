<?php

namespace App\Http\Controllers;

use App\Models\WmiSchool;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WmiSchoolController
{
    private function validated(Request $request, ?WmiSchool $school=null): array
    {
        $request->merge(collect($request->only('name_en','name_kh','remark'))->map(fn($value)=>is_string($value)?preg_replace('/\s+/u',' ',trim($value)):$value)->all());
        $data=$request->validate([
            'name_en'=>['required','string','max:255'], 'name_kh'=>['nullable','string','max:255'],
            'school_type'=>['required','in:Private,Public'], 'province_id'=>['nullable','integer','exists:tb_province,id'],
            'remark'=>['nullable','string','max:2000'],
        ]);
        $data['name_key']=hash('sha256',mb_strtolower($data['name_en']));
        validator($data,['name_key'=>[Rule::unique('wmi_schools')->ignore($school?->id)]],['name_key.unique'=>'This school already exists. Please edit the existing school.'])->validate();
        return $data;
    }

    public function store(Request $request)
    {
        WmiSchool::create($this->validated($request));
        return response()->json(['message'=>'School added successfully.'],201);
    }
    public function update(Request $request, WmiSchool $school)
    {
        $school->update($this->validated($request,$school));
        return response()->json(['message'=>'School updated successfully.']);
    }
    public function destroy(WmiSchool $school)
    {
        try {
            DB::transaction(function () use ($school) {
                $record=WmiSchool::whereKey($school->id)->lockForUpdate()->firstOrFail();
                abort_if(WmiSchool::linkedIds([$record->id])!==[],409,'This school is linked to data and cannot be deleted.');
                $record->delete();
            });
        } catch (QueryException $e) {
            if (in_array((string)$e->getCode(),['23000','23503'],true)) abort(409,'This school is linked to data and cannot be deleted.');
            throw $e;
        }
        return response()->json(['message'=>'School deleted successfully.']);
    }
}
