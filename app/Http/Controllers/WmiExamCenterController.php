<?php

namespace App\Http\Controllers;

use App\Models\WmiExamCenter;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\{Rule,ValidationException};

class WmiExamCenterController
{
    private function validated(Request $request, ?WmiExamCenter $center=null): array
    {
        $data=$request->validate(['wmi_year_id'=>['required','integer','exists:wmi_years,id'],
            'name'=>['required','string','max:255'],'location'=>['required','string','max:255']]);
        $data['name']=preg_replace('/\s+/u',' ',trim($data['name']));
        $data['location']=trim($data['location']);
        if ($data['name']==='' || $data['location']==='') throw ValidationException::withMessages(['name'=>'Enter an exam center and location.']);
        $data['name_key']=hash('sha256',mb_strtolower($data['name']));
        validator($data,['name_key'=>[Rule::unique('wmi_exam_centers','name_key')->where('wmi_year_id',$data['wmi_year_id'])->ignore($center?->id)]],
            ['name_key.unique'=>'This exam center is already assigned to the selected WMI year.'])->validate();
        return $data;
    }

    public function store(Request $request)
    {
        WmiExamCenter::create($this->validated($request));
        return response()->json(['message'=>'Exam center assigned successfully.'],201);
    }

    public function update(Request $request, WmiExamCenter $center)
    {
        $data=$this->validated($request,$center);
        DB::transaction(function () use ($center,$data) {
            $record=WmiExamCenter::whereKey($center->id)->lockForUpdate()->firstOrFail();
            abort_if($record->wmi_year_id!==(int)$data['wmi_year_id'] && WmiExamCenter::linkedIds([$record->id])!==[],409,'This exam center is linked to data. Its WMI year cannot be changed.');
            $record->update($data);
        });
        return response()->json(['message'=>'Exam center updated successfully.']);
    }

    public function destroy(WmiExamCenter $center)
    {
        try {
            DB::transaction(function () use ($center) {
                $record=WmiExamCenter::whereKey($center->id)->lockForUpdate()->firstOrFail();
                abort_if(WmiExamCenter::linkedIds([$record->id])!==[],409,'This exam center is linked to data and cannot be deleted.');
                $record->delete();
            });
        } catch (QueryException $e) {
            if (in_array((string)$e->getCode(),['23000','23503'],true)) abort(409,'This exam center is linked to data and cannot be deleted.');
            throw $e;
        }
        return response()->json(['message'=>'Exam center deleted successfully.']);
    }
}
